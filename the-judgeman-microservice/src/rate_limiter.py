"""
JudgeMan Microservice - Rate Limiting Module
Implements rate limiting to prevent input-based DoS attacks
"""
import time
import logging
from typing import Dict, Tuple
from collections import defaultdict

logger = logging.getLogger(__name__)


class RateLimiter:
    """Simple rate limiter for input processing"""
    
    def __init__(self, max_requests: int = 100, window_seconds: int = 60):
        """
        Initialize rate limiter
        
        Args:
            max_requests: Maximum requests per time window
            window_seconds: Time window in seconds
        """
        self.max_requests = max_requests
        self.window_seconds = window_seconds
        # Dictionary to track request timestamps by client
        self.request_history: Dict[str, list] = defaultdict(list)
    
    def is_allowed(self, client_id: str) -> Tuple[bool, dict]:
        """
        Check if a request is allowed for the given client
        
        Returns:
            Tuple of (is_allowed, info_dict)
        """
        now = time.time()
        window_start = now - self.window_seconds
        
        # Clean old entries outside the window
        self.request_history[client_id] = [
            ts for ts in self.request_history[client_id]
            if ts > window_start
        ]
        
        # Get count in current window
        request_count = len(self.request_history[client_id])
        
        info = {
            'request_count': request_count,
            'max_allowed': self.max_requests,
            'window_seconds': self.window_seconds,
            'allowed': request_count < self.max_requests
        }
        
        if request_count >= self.max_requests:
            logger.warning(
                f"Rate limit exceeded for client {client_id}: "
                f"{request_count}/{self.max_requests} requests in {self.window_seconds}s"
            )
            return False, info
        
        # Add current request
        self.request_history[client_id].append(now)
        
        return True, info
    
    def cleanup_expired(self) -> None:
        """Remove expired entries to prevent memory bloat"""
        now = time.time()
        window_start = now - self.window_seconds
        
        # Remove clients with no recent requests
        expired_clients = []
        for client_id, timestamps in self.request_history.items():
            self.request_history[client_id] = [
                ts for ts in timestamps if ts > window_start
            ]
            if not self.request_history[client_id]:
                expired_clients.append(client_id)
        
        for client_id in expired_clients:
            del self.request_history[client_id]
        
        if expired_clients:
            logger.debug(f"Cleaned up {len(expired_clients)} expired rate limit entries")


class InputRateLimiter:
    """Rate limiter specifically for input bytes processed"""
    
    def __init__(self, max_bytes_per_minute: int = 10_000_000):  # 10MB per minute
        """
        Initialize input bytes rate limiter
        
        Args:
            max_bytes_per_minute: Maximum bytes per minute
        """
        self.max_bytes = max_bytes_per_minute
        self.byte_history: Dict[str, list] = defaultdict(list)
    
    def check_input_size(self, job_id: str, input_size: int) -> Tuple[bool, dict]:
        """
        Check if input size is within rate limit
        
        Returns:
            Tuple of (is_allowed, info_dict)
        """
        now = time.time()
        window_start = now - 60  # 1 minute window
        
        # Clean old entries
        self.byte_history[job_id] = [
            (ts, size) for ts, size in self.byte_history[job_id]
            if ts > window_start
        ]
        
        # Calculate total bytes in window
        total_bytes = sum(size for _, size in self.byte_history[job_id])
        new_total = total_bytes + input_size
        
        info = {
            'current_bytes': total_bytes,
            'new_input_size': input_size,
            'new_total': new_total,
            'max_allowed': self.max_bytes,
            'window_seconds': 60,
            'allowed': new_total <= self.max_bytes
        }
        
        if new_total > self.max_bytes:
            logger.warning(
                f"Input byte rate limit exceeded for job {job_id}: "
                f"{new_total}/{self.max_bytes} bytes in 60s"
            )
            return False, info
        
        # Record this input
        self.byte_history[job_id].append((now, input_size))
        
        return True, info
