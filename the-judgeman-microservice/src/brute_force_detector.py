"""
JudgeMan Microservice - Brute Force Detection Module
Detects and blocks malicious users attempting attacks
"""
import time
import logging
from typing import Dict, Tuple, List
from collections import defaultdict

logger = logging.getLogger(__name__)


class BruteForceDetector:
    """Detects brute force attack patterns"""
    
    def __init__(
        self,
        failed_attempts_threshold: int = 5,
        timeframe_seconds: int = 300,
        blockade_seconds: int = 900
    ):
        """
        Initialize brute force detector
        
        Args:
            failed_attempts_threshold: Number of failures before blocking
            timeframe_seconds: Time window for counting failures (5 minutes)
            blockade_seconds: How long to block (15 minutes)
        """
        self.threshold = failed_attempts_threshold
        self.timeframe = timeframe_seconds
        self.blockade = blockade_seconds
        
        # Track failed attempts by client
        self.failed_attempts: Dict[str, List[float]] = defaultdict(list)
        # Track blocked clients
        self.blocked_clients: Dict[str, float] = {}
    
    def is_blocked(self, client_id: str) -> Tuple[bool, dict]:
        """
        Check if client is blocked
        
        Returns:
            Tuple of (is_blocked, info_dict)
        """
        now = time.time()
        
        # Check if client is in blockade list
        if client_id in self.blocked_clients:
            blockade_end = self.blocked_clients[client_id]
            if now < blockade_end:
                remaining = blockade_end - now
                logger.warning(
                    f"Client {client_id} is blocked (remaining: {remaining:.0f}s)"
                )
                return True, {
                    'blocked': True,
                    'reason': 'Too many failed attempts',
                    'remaining_seconds': remaining
                }
            else:
                # Blockade expired, unblock
                del self.blocked_clients[client_id]
                logger.info(f"Client {client_id} blockade expired, unblocked")
        
        return False, {'blocked': False}
    
    def record_failed_attempt(
        self,
        client_id: str,
        reason: str = "Invalid input"
    ) -> Tuple[bool, dict]:
        """
        Record a failed attempt
        
        Returns:
            Tuple of (should_block, info_dict)
        """
        now = time.time()
        window_start = now - self.timeframe
        
        # Clean old attempts outside window
        self.failed_attempts[client_id] = [
            ts for ts in self.failed_attempts[client_id]
            if ts > window_start
        ]
        
        # Add current failure
        self.failed_attempts[client_id].append(now)
        failure_count = len(self.failed_attempts[client_id])
        
        logger.warning(
            f"Client {client_id} failed attempt #{failure_count}/{self.threshold}: {reason}"
        )
        
        info = {
            'failure_count': failure_count,
            'threshold': self.threshold,
            'timeframe_seconds': self.timeframe,
            'blocked': False
        }
        
        # Check if threshold exceeded
        if failure_count >= self.threshold:
            # Block client
            blockade_end = now + self.blockade
            self.blocked_clients[client_id] = blockade_end
            
            logger.error(
                f"Client {client_id} BLOCKED after {failure_count} failed attempts. "
                f"Blockade until {time.strftime('%Y-%m-%d %H:%M:%S', time.localtime(blockade_end))}"
            )
            
            info['blocked'] = True
            info['blockade_seconds'] = self.blockade
            
            return True, info
        
        return False, info
    
    def record_success(self, client_id: str) -> None:
        """Clear failure counter for successful attempt"""
        if client_id in self.failed_attempts:
            self.failed_attempts[client_id] = []
            logger.debug(f"Client {client_id}: Failed attempt counter reset")
    
    def get_stats(self, client_id: str) -> dict:
        """Get statistics for a client"""
        now = time.time()
        window_start = now - self.timeframe
        
        # Clean old attempts
        self.failed_attempts[client_id] = [
            ts for ts in self.failed_attempts[client_id]
            if ts > window_start
        ]
        
        failure_count = len(self.failed_attempts[client_id])
        is_blocked = client_id in self.blocked_clients
        
        info = {
            'client_id': client_id,
            'failure_count': failure_count,
            'threshold': self.threshold,
            'is_blocked': is_blocked,
            'remaining_blockade': None
        }
        
        if is_blocked:
            remaining = self.blocked_clients[client_id] - now
            info['remaining_blockade'] = max(0, remaining)
        
        return info
    
    def cleanup_expired(self) -> None:
        """Clean up expired blockades to free memory"""
        now = time.time()
        expired = []
        
        for client_id, blockade_end in self.blocked_clients.items():
            if now > blockade_end:
                expired.append(client_id)
        
        for client_id in expired:
            del self.blocked_clients[client_id]
        
        if expired:
            logger.debug(f"Cleaned up {len(expired)} expired blockades")
