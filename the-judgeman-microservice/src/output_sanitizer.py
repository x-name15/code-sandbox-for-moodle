"""
JudgeMan Microservice - Output Sanitizer
Sanitizes execution output to hide internal paths and system details.
"""
import re
import logging
from typing import Tuple

from .config import Config

logger = logging.getLogger(__name__)

class OutputSanitizer:
    """Sanitizes container output before returning to user"""
    
    def __init__(self, config: Config):
        self.config = config
        
        # Paths to hide or simplify
        self.path_replacements = [
            (r'/jail/main\.(py|js|java|c|cpp|asm|php)', r'main.\1'),  # Simplify main file path
            (r'/jail/', r'./'),                                       # Simplify other jail paths
            (r'/shared_data/job_[a-f0-9]+_[a-f0-9]+/', r'./'),       # specific job folder
            (r'/shared_data/', r'./'),                                # generic shared data
            (r'/app/src/.*', r'[INTERNAL]'),                         # Hide app source code
            (r'/usr/local/lib/.*', r'[LIB]'),                        # Hide system libraries (optional, maybe too aggressive?)
        ]
        
        # Patterns to completely redact
        self.redaction_patterns = [
            # Environment variables format KEY=VALUE (simple heuristic)
            (r'([A-Z_]+=[a-zA-Z0-9_\-\.]+)', r'[ENV_VAR_REDACTED]'),
        ]

    def sanitize(self, text: str) -> str:
        """
        Sanitize the output string by removing internal paths and sensitive info.
        """
        if not text:
            return ""
            
        sanitized = text
        
        # 1. Apply path replacements
        for pattern, replacement in self.path_replacements:
            sanitized = re.sub(pattern, replacement, sanitized)
            
        # 2. Limit output length 
        limit = getattr(self.config, 'MAX_OUTPUT_SIZE', 10000)
        if len(sanitized) > limit:
             sanitized = sanitized[:limit] + "\n... [Output Truncated]"
             
        return sanitized

    def sanitize_result(self, stdout: str, stderr: str) -> Tuple[str, str]:
        """Sanitize both stdout and stderr"""
        return self.sanitize(stdout), self.sanitize(stderr)
