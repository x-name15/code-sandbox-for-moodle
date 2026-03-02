"""
JudgeMan Microservice - Callback Handler Module
Handles HTTP callbacks to Moodle or other systems
"""
import logging
from typing import Dict, Any, Optional
from dataclasses import dataclass, asdict

import requests

from .config import Config

logger = logging.getLogger(__name__)


@dataclass
class ExecutionResult:
    """Data class for execution results"""
    job_id: str
    status: str  # 'completed' | 'failed' | 'timeout' | 'error'
    exit_code: int
    stdout: str
    stderr: str
    input_data: Optional[str] = None
    execution_time: Optional[float] = None
    action: str = 'run'


class CallbackHandler:
    """Handles HTTP callbacks for job results"""
    
    def __init__(self, config: Config):
        self.config = config
        self.session = requests.Session()
        self.session.headers.update({
            'Content-Type': 'application/json',
            'User-Agent': 'JudgeMan-Worker/1.0'
        })
    
    def send_callback(
        self, 
        callback_url: str, 
        callback_token: str, 
        result: ExecutionResult
    ) -> bool:
        """
        Send execution result to callback URL
        
        Returns:
            True if callback was successful, False otherwise
        """
        if not callback_url or not callback_token:
            logger.warning(f"Job {result.job_id}: No callback URL/token provided")
            return False
        
        # Fix URL for Docker environment
        fixed_url = self._fix_callback_url(callback_url)
        
        # Build payload
        payload = {
            'job_id': result.job_id,
            'token': callback_token,
            'status': result.status,
            'stdout': self._truncate_output(result.stdout),
            'stderr': self._truncate_output(result.stderr),
            'exitcode': result.exit_code,
            'execution_time': result.execution_time,
            'action': result.action,
            'inputdata': result.input_data # Send inputdata so teacher can see what was tested
        }
        
        try:
            logger.info(f"Job {result.job_id}: Sending callback to {fixed_url}")
            
            response = self.session.post(
                fixed_url,
                json=payload,
                timeout=10
            )
            
            if response.status_code == 200:
                logger.info(f"Job {result.job_id}: Callback sent successfully")
                return True
            else:
                logger.warning(
                    f"Job {result.job_id}: Callback failed with status {response.status_code}: "
                    f"{response.text[:200]}"
                )
                return False
        
        except requests.exceptions.Timeout:
            logger.error(f"Job {result.job_id}: Callback timeout")
            return False
        except requests.exceptions.ConnectionError as e:
            logger.error(f"Job {result.job_id}: Callback connection error: {e}")
            return False
        except requests.exceptions.RequestException as e:
            logger.error(f"Job {result.job_id}: Callback request failed: {e}")
            return False
        except Exception as e:
            logger.error(f"Job {result.job_id}: Unexpected callback error: {e}", exc_info=True)
            return False
    
    def _fix_callback_url(self, url: str) -> str:
        """
        Fix callback URL to work from inside Docker container
        Replaces localhost/127.0.0.1 with host.docker.internal
        """
        if not url:
            return url
        
        if self.config.is_running_in_docker():
            original_url = url
            url = url.replace('localhost', 'host.docker.internal')
            url = url.replace('127.0.0.1', 'host.docker.internal')
            
            if url != original_url:
                logger.debug(f"Callback URL adjusted for Docker: {original_url} → {url}")
        
        return url
    
    def cleanup(self) -> None:
        """Cleanup resources before shutdown"""
        try:
            self.session.close()
            logger.info("Callback handler cleanup completed")
        except Exception as e:
            logger.warning(f"Callback handler cleanup error: {e}")
    
    def _truncate_output(self, output: str) -> str:
        """Truncate output to maximum size"""
        if not output:
            return ""
        
        max_size = self.config.MAX_OUTPUT_SIZE
        if len(output) > max_size:
            truncated = output[:max_size]
            logger.debug(f"Output truncated from {len(output)} to {max_size} bytes")
            return truncated + "\n...[output truncated]"
        
        return output
    
    def cleanup(self) -> None:
        """Cleanup HTTP session"""
        try:
            self.session.close()
            logger.debug("HTTP session closed")
        except Exception as e:
            logger.warning(f"Error closing HTTP session: {e}")
