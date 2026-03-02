"""
JudgeMan Microservice - Worker Module
RabbitMQ consumer that processes code execution jobs
"""
import json
import time
import logging
from typing import Dict, Any, Optional

import pika
from pika.adapters.blocking_connection import BlockingChannel
from pika.spec import Basic, BasicProperties

from .config import Config
from .docker_manager import DockerManager
from .input_validator import InputValidator
from .rate_limiter import RateLimiter, InputRateLimiter
from .brute_force_detector import BruteForceDetector
from .input_speed_limiter import InputSpeedLimiter, InputPerSecondLimiter
from .input_sanitizer import InputSanitizer
from .output_sanitizer import OutputSanitizer
from .callback_handler import CallbackHandler, ExecutionResult

logger = logging.getLogger(__name__)


class JobWorker:
    """RabbitMQ worker that processes code execution jobs"""
    
    def __init__(self, config: Config):
        self.config = config
        self.docker_manager = DockerManager(config)
        self.input_validator = InputValidator(config)
        self.rate_limiter = RateLimiter(max_requests=1000, window_seconds=60)
        self.input_rate_limiter = InputRateLimiter(max_bytes_per_minute=50_000_000)  # 50MB/min
        self.brute_force_detector = BruteForceDetector(
            failed_attempts_threshold=5,
            timeframe_seconds=300,
            blockade_seconds=900
        )
        self.input_speed_limiter = InputSpeedLimiter(max_bytes_per_second=1_000_000)
        self.input_per_second_limiter = InputPerSecondLimiter(max_inputs_per_second=5.0)
        self.input_sanitizer = InputSanitizer()
        self.output_sanitizer = OutputSanitizer(config)
        self.callback_handler = CallbackHandler(config)
        self.connection: Optional[pika.BlockingConnection] = None
        self.channel: Optional[BlockingChannel] = None
    
    def connect_to_rabbitmq(self) -> None:
        """Establish connection to RabbitMQ"""
        credentials = pika.PlainCredentials(
            self.config.RABBIT_USER, 
            self.config.RABBIT_PASS
        )
        
        params = pika.ConnectionParameters(
            host=self.config.RABBIT_HOST,
            port=self.config.RABBIT_PORT,
            credentials=credentials,
            heartbeat=self.config.RABBIT_HEARTBEAT
        )
        
        self.connection = pika.BlockingConnection(params)
        self.channel = self.connection.channel()
        
        # Declare queue
        self.channel.queue_declare(
            queue=self.config.RABBIT_QUEUE_NAME, 
            durable=True
        )
        
        # Set QoS
        self.channel.basic_qos(
            prefetch_count=self.config.RABBIT_PREFETCH_COUNT
        )
        
        logger.info(
            f"Connected to RabbitMQ at {self.config.RABBIT_HOST}:{self.config.RABBIT_PORT}, "
            f"queue={self.config.RABBIT_QUEUE_NAME}"
        )
    
    def process_job(
        self, 
        ch: BlockingChannel, 
        method: Basic.Deliver, 
        properties: BasicProperties, 
        body: bytes
    ) -> None:
        """Process a single job from the queue"""
        start_time = time.time()
        
        # Parse message
        try:
            payload = json.loads(body)
        except json.JSONDecodeError as e:
            logger.error(f"Invalid JSON payload: {e}")
            ch.basic_ack(delivery_tag=method.delivery_tag)
            return
        
        # DEBUG: Log the complete payload (sanitized)
        logger.info(f"[PAYLOAD DEBUG] Received payload with keys: {list(payload.keys())}")
        logger.debug(f"[PAYLOAD DEBUG] Full payload: {json.dumps(payload, indent=2, default=str)}")
        
        # Extract job data
        job_id = payload.get('job_id', 'unknown')
        language = payload.get('language')
        code = payload.get('code', '')
        callback_url = payload.get('callback_url')
        callback_token = payload.get('callback_token')
        action = payload.get('action', 'run')  # 'run' (test) or 'submit' (autograding)
        attempt_id = payload.get('attempt_id')  # For Moodle compatibility
        client_id = payload.get('user_id', payload.get('client_id', job_id))  # Extract client identifier
        
        # CRITICAL: Only use inputdata in 'run' mode (test mode with user inputs)
        # UPDATE: Now both 'run' and 'submit' modes use inputs if the user provided them.
        # The teacher needs to see the execution with those inputs.
        
        # Check multiple possible field names from Moodle (handle None vs empty string correctly)
        # Use explicit None checks - empty string "" is valid input data!
        raw_inputdata = payload.get('inputdata')
        if raw_inputdata is None:
            raw_inputdata = payload.get('input_data')
        if raw_inputdata is None:
            raw_inputdata = payload.get('stdin')
        if raw_inputdata is None:
            raw_inputdata = payload.get('inputs')
        if raw_inputdata is None:
            raw_inputdata = ''
        
        input_data = raw_inputdata
        
        # DEBUG: Log exactly what we received
        logger.info(f"Job {job_id}: Raw inputdata from payload: {repr(raw_inputdata[:100] if raw_inputdata else 'EMPTY')}")

        # Fallback for submit mode with no inputs (prevents EOFError)
        if not input_data:
            logger.warning(f"Job {job_id}: No inputs provided. Injecting newlines to prevent EOFError.")
            input_data = "\n" * 100
        
        logger.info(f"Processing Job {job_id}: language={language}, action={action}")
        if input_data:
            logger.info(f"Job {job_id}: TEST mode - processing {len(input_data)} bytes of user input")
            logger.debug(f"Job {job_id}: Input data preview: {repr(input_data[:200])}")
        else:
            logger.info(f"Job {job_id}: AUTOGRADING mode - no interactive input")
        
        # 1. Brute force check
        is_blocked, brute_info = self.brute_force_detector.is_blocked(client_id)
        if is_blocked:
            msg = f"Client blocked due to too many failed attempts. Remaining blockade: {brute_info.get('remaining_seconds', 0):.0f}s"
            logger.error(f"Job {job_id}: {msg}")
            self._send_error_callback(
                job_id, callback_url, callback_token, action, msg
            )
            ch.basic_ack(delivery_tag=method.delivery_tag)
            return
        
        # 2. General rate limit check
        is_allowed, rate_info = self.rate_limiter.is_allowed(client_id)
        if not is_allowed:
            msg = f"Rate limit exceeded: {rate_info['request_count']}/{rate_info['max_allowed']} requests"
            logger.warning(f"Job {job_id}: {msg}")
            self.brute_force_detector.record_failed_attempt(client_id, msg)
            self._send_error_callback(
                job_id, callback_url, callback_token, action, msg
            )
            ch.basic_ack(delivery_tag=method.delivery_tag)
            return
        
        # 3. Validate language
        lang_config = self.config.get_language_config(language)
        if not lang_config:
            msg = f"Unsupported language '{language}'"
            logger.warning(f"Job {job_id}: {msg}")
            self.brute_force_detector.record_failed_attempt(client_id, msg)
            self._send_error_callback(
                job_id, callback_url, callback_token, action, msg
            )
            ch.basic_ack(delivery_tag=method.delivery_tag)
            return
        
        # 4. Validate input data if present
        if input_data:
            # 4a. Check per-second input submissions
            can_submit, submit_info = self.input_per_second_limiter.can_submit(client_id)
            if not can_submit:
                msg = f"Input submission too frequent. Wait {submit_info['wait_time']:.1f}s"
                logger.warning(f"Job {job_id}: {msg}")
                self.brute_force_detector.record_failed_attempt(client_id, msg)
                self._send_error_callback(
                    job_id, callback_url, callback_token, action, msg
                )
                ch.basic_ack(delivery_tag=method.delivery_tag)
                return
            
            # 4b. Check input byte rate limit
            is_allowed, bytes_info = self.input_rate_limiter.check_input_size(client_id, len(input_data))
            if not is_allowed:
                msg = (f"Input size rate limit exceeded: {bytes_info['new_total']}/"
                       f"{bytes_info['max_allowed']} bytes in 60s")
                logger.warning(f"Job {job_id}: {msg}")
                self.brute_force_detector.record_failed_attempt(client_id, msg)
                self._send_error_callback(
                    job_id, callback_url, callback_token, action, msg
                )
                ch.basic_ack(delivery_tag=method.delivery_tag)
                return
            
            # 4c. General input validation (size, encoding, dangerous patterns)
            is_valid, error_msg, report = self.input_validator.validate(job_id, input_data)
            if not is_valid:
                logger.error(f"Job {job_id}: Input validation failed - {error_msg}")
                safe_display = self.input_validator.sanitize_for_logging(input_data)
                logger.debug(f"Job {job_id}: Rejected input: {safe_display}")
                self.brute_force_detector.record_failed_attempt(client_id, error_msg)
                self._send_error_callback(
                    job_id, callback_url, callback_token, action,
                    f"Invalid input: {error_msg}"
                )
                ch.basic_ack(delivery_tag=method.delivery_tag)
                return
            
            # 4d. Language-specific validation
            is_valid, error_msg = self.input_validator.validate_for_language(job_id, input_data, language)
            if not is_valid:
                logger.error(f"Job {job_id}: Language-specific validation failed - {error_msg}")
                self.brute_force_detector.record_failed_attempt(client_id, f"Language validation: {error_msg}")
                self._send_error_callback(
                    job_id, callback_url, callback_token, action,
                    f"Invalid input for {language}: {error_msg}"
                )
                ch.basic_ack(delivery_tag=method.delivery_tag)
                return
            
            # 4e. Check for suspicious sanitization patterns
            suspicious = self.input_sanitizer.check_for_suspicious_patterns(input_data, job_id)
            if suspicious['suspicious_patterns'] or suspicious['potentially_dangerous_chars']:
                logger.warning(f"Job {job_id}: Suspicious patterns detected: {suspicious}")
                # Log but continue - these aren't necessarily blocking
            
            # 4f. Sanitize input
            clean_input = self.input_sanitizer.sanitize_for_safe_input(input_data)
            if clean_input != input_data:
                logger.debug(f"Job {job_id}: Input sanitized ({len(input_data)} -> {len(clean_input)} bytes)")
                input_data = clean_input
            
            # Mark success for brute force counter
            self.brute_force_detector.record_success(client_id)
            safe_display = self.input_validator.sanitize_for_logging(input_data)
            logger.info(f"Job {job_id}: Input validated ✓ - {safe_display}")
        
        workspace_path = None
        
        try:
            # Create workspace (with input data if provided)
            workspace_path = self.docker_manager.create_job_workspace(
                job_id, code, lang_config['filename'], input_data
            )
            
            logger.info(f"Job {job_id}: Calling run_sandbox with input_data length={len(input_data) if input_data else 0}")
            
            # Execute in sandbox
            exit_code, logs = self.docker_manager.run_sandbox(
                job_id=job_id,
                workspace_path=workspace_path,
                image=lang_config['image'],
                command=lang_config['command'],
                input_data=input_data
            )
            
            # Calculate execution time
            execution_time = time.time() - start_time
            
            # Determine status
            raw_stdout, raw_stderr = "", ""
            
            # CRITICAL FIX for Moodle Compatibility
            # Moodle expects 'completed' status even if the user code fails (exit_code != 0)
            # 'failed' status is reserved for system errors or timeouts
            if exit_code == 124:
                status = 'timeout'
                raw_stderr = logs
            else:
                status = 'completed'  # Always completed if container finished execution
                if exit_code == 0:
                    raw_stdout = logs
                else:
                    raw_stderr = logs
            
            # Sanitize output
            stdout, stderr = self.output_sanitizer.sanitize_result(raw_stdout, raw_stderr)
            
            logger.info(
                f"Job {job_id}: Finished with status={status}, "
                f"exit_code={exit_code}, time={execution_time:.2f}s"
            )
            
            # Send callback
            result = ExecutionResult(
                job_id=job_id,
                status=status,
                exit_code=exit_code,
                stdout=stdout,
                stderr=stderr,
                input_data=input_data, # Pass input_data to callback
                execution_time=execution_time,
                action=action
            )
            
            if callback_url and callback_token:
                self.callback_handler.send_callback(callback_url, callback_token, result)
            else:
                logger.warning(f"Job {job_id}: No callback configured")
        
        except Exception as e:
            logger.error(f"Job {job_id}: Unexpected error: {e}", exc_info=True)
            self._send_error_callback(
                job_id, callback_url, callback_token, action,
                f"Internal error: {str(e)}"
            )
        
        finally:
            # Cleanup
            if workspace_path:
                self.docker_manager.cleanup_workspace(job_id, workspace_path)
            
            # Acknowledge message
            ch.basic_ack(delivery_tag=method.delivery_tag)
    
    def _send_error_callback(
        self, 
        job_id: str, 
        callback_url: Optional[str], 
        callback_token: Optional[str], 
        action: str,
        error_message: str
    ) -> None:
        """Send error callback (sanitized)"""
        # Sanitize sensitive info from error messages
        sanitized_error = self.output_sanitizer.sanitize(error_message)
        
        if callback_url and callback_token:
            result = ExecutionResult(
                job_id=job_id,
                status='error',
                exit_code=1,
                stdout='',
                stderr=sanitized_error,
                action=action
            )
            self.callback_handler.send_callback(callback_url, callback_token, result)
    
    def start(self) -> None:
        """Start consuming jobs from the queue"""
        logger.info(f"Worker started. Listening on queue: {self.config.RABBIT_QUEUE_NAME}")
        logger.info(f"Environment: {self.config.ENVIRONMENT}")
        logger.info(f"Running in Docker: {self.config.is_running_in_docker()}")
        
        while True:
            try:
                if not self.connection or self.connection.is_closed:
                    self.connect_to_rabbitmq()
                
                # Start consuming
                self.channel.basic_consume(
                    queue=self.config.RABBIT_QUEUE_NAME,
                    on_message_callback=self.process_job
                )
                
                logger.info("✓ Worker ready to receive jobs")
                self.channel.start_consuming()
            
            except pika.exceptions.AMQPConnectionError as e:
                logger.error(f"RabbitMQ connection error: {e}. Retrying in 5s...")
                time.sleep(5)
            except KeyboardInterrupt:
                logger.info("Worker shutdown requested")
                break
            except Exception as e:
                logger.critical(f"Fatal error: {e}", exc_info=True)
                time.sleep(5)
    
    def stop(self) -> None:
        """Stop the worker gracefully"""
        logger.info("Stopping worker...")
        
        try:
            if self.channel and not self.channel.is_closed:
                self.channel.stop_consuming()
                self.channel.close()
        except Exception as e:
            logger.warning(f"Error closing channel: {e}")
        
        try:
            if self.connection and not self.connection.is_closed:
                self.connection.close()
        except Exception as e:
            logger.warning(f"Error closing connection: {e}")
        
        self.docker_manager.cleanup()
        self.callback_handler.cleanup()
        
        logger.info("Worker stopped")
