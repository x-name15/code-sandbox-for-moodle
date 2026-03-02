"""
JudgeMan Microservice - Input Speed Limiter
Ensures inputs are not sent faster than expected (prevents DoS)
"""
import time
import logging
from typing import Tuple, Optional

logger = logging.getLogger(__name__)


class InputSpeedLimiter:
    """Limits input data speed to prevent DoS attacks"""
    
    def __init__(self, max_bytes_per_second: int = 1_000_000):  # 1MB/s default
        """
        Initialize speed limiter
        
        Args:
            max_bytes_per_second: Maximum bytes per second allowed
        """
        self.max_speed = max_bytes_per_second
    
    def check_speed(
        self,
        job_id: str,
        input_size: int,
        elapsed_time: float
    ) -> Tuple[bool, dict]:
        """
        Check if input speed is within limits
        
        Args:
            job_id: Job identifier
            input_size: Size of input in bytes
            elapsed_time: Time taken to provide input in seconds
        
        Returns:
            Tuple of (is_within_limit, info_dict)
        """
        if elapsed_time <= 0:
            elapsed_time = 0.001  # Avoid division by zero
        
        actual_speed = input_size / elapsed_time
        
        info = {
            'input_size': input_size,
            'elapsed_time': elapsed_time,
            'actual_speed_bytes_per_sec': actual_speed,
            'max_allowed_bytes_per_sec': self.max_speed,
            'within_limit': actual_speed <= self.max_speed
        }
        
        if actual_speed > self.max_speed:
            logger.warning(
                f"Job {job_id}: Input speed exceeds limit - "
                f"{actual_speed:.0f} bytes/s > {self.max_speed} bytes/s"
            )
            return False, info
        
        return True, info
    
    def get_expected_time(self, input_size: int) -> float:
        """
        Calculate expected minimum time for given input size
        
        Args:
            input_size: Size in bytes
        
        Returns:
            Minimum expected time in seconds
        """
        return input_size / self.max_speed


class InputPerSecondLimiter:
    """Limit number of input submissions per second per client"""
    
    def __init__(self, max_inputs_per_second: float = 5.0):
        """
        Initialize
        
        Args:
            max_inputs_per_second: Maximum input submissions per second
        """
        self.max_rate = max_inputs_per_second
        self.min_interval = 1.0 / max_inputs_per_second  # Minimum seconds between inputs
        self.last_input_time = {}
    
    def can_submit(self, client_id: str) -> Tuple[bool, dict]:
        """
        Check if client can submit input now
        
        Returns:
            Tuple of (can_submit, info_dict)
        """
        now = time.time()
        
        if client_id not in self.last_input_time:
            # First submission
            self.last_input_time[client_id] = now
            return True, {
                'can_submit': True,
                'wait_time': 0
            }
        
        time_since_last = now - self.last_input_time[client_id]
        
        if time_since_last < self.min_interval:
            wait_time = self.min_interval - time_since_last
            logger.debug(
                f"Client {client_id}: Input rate limit - must wait {wait_time:.2f}s"
            )
            return False, {
                'can_submit': False,
                'last_submission_seconds_ago': time_since_last,
                'wait_time': wait_time,
                'max_per_second': self.max_rate
            }
        
        # Update last submission time
        self.last_input_time[client_id] = now
        
        return True, {
            'can_submit': True,
            'wait_time': 0
        }
    
    def cleanup(self) -> None:
        """Clean up old timestamps to free memory"""
        now = time.time()
        # Remove entries older than 1 hour
        cutoff = now - 3600
        expired = [
            cid for cid, last_time in self.last_input_time.items()
            if last_time < cutoff
        ]
        
        for cid in expired:
            del self.last_input_time[cid]
        
        if expired:
            logger.debug(f"Cleaned up {len(expired)} expired input rate limit entries")
