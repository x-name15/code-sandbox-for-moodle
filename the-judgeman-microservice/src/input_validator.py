"""
JudgeMan Microservice - Input Validation Module
Handles secure input validation and sanitization
"""
import re
import logging
from typing import Tuple, Optional
import unicodedata

from .config import Config

logger = logging.getLogger(__name__)


class InputValidator:
    """Validates and sanities user inputs for security"""
    
    # Dangerous patterns that suggest code injection attempts
    DANGEROUS_PATTERNS = [
        r'\$\(.*\)',           # Command substitution: $(...)
        r'`.*`',               # Backtick command substitution
        r'[;&|<>]',            # Shell operators (unless escaped)
        r'\\x',                # Escape sequences
        r'__import__',         # Python code injection
        r'exec\s*\(',          # Python exec
        r'eval\s*\(',          # Python eval
        r'require\s*\(',       # Node.js require
        r'system\s*\(',        # C system calls
        r'popen\s*\(',         # C popen
    ]
    
    # Allowed control characters (only newline and carriage return in input)
    ALLOWED_CONTROL_CHARS = {'\n', '\r', '\t'}
    
    def __init__(self, config: Config):
        """Initialize validator with config"""
        self.config = config
        self.compiled_patterns = [re.compile(pattern, re.IGNORECASE) 
                                  for pattern in self.DANGEROUS_PATTERNS]
    
    def validate(self, job_id: str, input_data: str) -> Tuple[bool, Optional[str], dict]:
        """
        Validate input data for security
        
        Returns:
            Tuple of (is_valid, error_message, security_report)
        """
        report = {
            'size': len(input_data),
            'lines': input_data.count('\n') + 1,
            'warnings': [],
            'blocked_patterns': []
        }
        
        # 1. Check size limits
        if len(input_data) > self.config.MAX_INPUT_SIZE:
            msg = f"Input exceeds maximum size ({len(input_data)} > {self.config.MAX_INPUT_SIZE})"
            logger.warning(f"Job {job_id}: Security violation - {msg}")
            return False, msg, report
        
        # 2. Check line count
        lines = input_data.count('\n') + 1
        if lines > self.config.MAX_INPUT_LINES:
            msg = f"Input exceeds maximum lines ({lines} > {self.config.MAX_INPUT_LINES})"
            logger.warning(f"Job {job_id}: Security violation - {msg}")
            return False, msg, report
        
        # 3. Validate UTF-8 encoding
        try:
            input_data.encode('utf-8').decode('utf-8')
        except UnicodeDecodeError as e:
            msg = f"Invalid UTF-8 encoding: {str(e)}"
            logger.warning(f"Job {job_id}: Encoding violation - {msg}")
            return False, msg, report
        
        # 4. Check for null bytes (potential buffer overflow)
        if '\x00' in input_data:
            logger.warning(f"Job {job_id}: Null byte detected in input")
            report['warnings'].append("Null byte detected")
        
        # 5. Check for suspicious control characters
        suspicious_chars = []
        for char in input_data:
            if ord(char) < 32 and char not in self.ALLOWED_CONTROL_CHARS:
                suspicious_chars.append(f"0x{ord(char):02x}")
        
        if suspicious_chars:
            logger.warning(f"Job {job_id}: Suspicious control chars: {suspicious_chars}")
            report['warnings'].append(f"Suspicious control characters: {suspicious_chars}")
        
        # 6. Scan for dangerous patterns
        for pattern_obj, pattern_str in zip(self.compiled_patterns, self.DANGEROUS_PATTERNS):
            matches = pattern_obj.findall(input_data)
            if matches:
                logger.warning(f"Job {job_id}: Dangerous pattern detected: {pattern_str}")
                report['blocked_patterns'].append({
                    'pattern': pattern_str,
                    'matches': matches[:5]  # Limit to first 5 matches
                })
        
        is_valid = len(report['blocked_patterns']) == 0
        
        if not is_valid:
            msg = f"Input contains blocked security patterns"
            logger.warning(f"Job {job_id}: Blocked patterns - {report['blocked_patterns']}")
            return False, msg, report
        
        # 7. Check for suspicious Unicode (homograph attacks, zero-width chars)
        suspicious_unicode = self._check_suspicious_unicode(input_data)
        if suspicious_unicode:
            logger.warning(f"Job {job_id}: Suspicious Unicode detected: {suspicious_unicode}")
            report['warnings'].append(f"Suspicious Unicode: {suspicious_unicode}")
        
        # Log successful validation
        logger.debug(f"Job {job_id}: Input validation passed - {report}")
        
        return True, None, report
    
    def _check_suspicious_unicode(self, text: str) -> list:
        """Detect suspicious Unicode characters"""
        suspicious = []
        
        for char in text:
            # Zero-width characters
            if unicodedata.category(char) in ('Cf', 'Cc'):
                if char not in ('\n', '\r', '\t'):
                    suspicious.append(f"Zero-width/control: U+{ord(char):04X}")
            
            # Right-to-left override
            if char == '\u202E':
                suspicious.append("Right-to-left override detected")
        
        return suspicious[:5]  # Return first 5
    
    def sanitize_for_logging(self, input_data: str, max_length: int = 200) -> str:
        """
        Sanitize input for safe logging (truncate and escape)
        
        Args:
            input_data: Input to sanitize
            max_length: Maximum length to show
        
        Returns:
            Safe string for logging
        """
        # Truncate
        display = input_data[:max_length]
        if len(input_data) > max_length:
            display += f"... (+{len(input_data) - max_length} chars)"
        
        # Replace control characters for display
        display = display.replace('\n', '\\n').replace('\r', '\\r').replace('\t', '\\t')
        
        return display
    
    def validate_for_language(
        self,
        job_id: str,
        input_data: str,
        language: str
    ) -> Tuple[bool, Optional[str]]:
        """
        Language-specific input validation
        
        Returns:
            Tuple of (is_valid, error_message)
        """
        language_lower = language.lower()
        
        # Python-specific validation
        if language_lower == 'python':
            return self._validate_python_input(job_id, input_data)
        
        # JavaScript-specific validation
        elif language_lower in ('javascript', 'js', 'node'):
            return self._validate_javascript_input(job_id, input_data)
        
        # C/C++-specific validation
        elif language_lower in ('c', 'cpp', 'c++'):
            return self._validate_c_input(job_id, input_data)
        
        # Default: no language-specific restrictions
        return True, None
    
    def _validate_python_input(self, job_id: str, input_data: str) -> Tuple[bool, Optional[str]]:
        """Python-specific input validation"""
        dangerous_keywords = [
            '__builtins__', '__import__', '__code__', '__globals__',
            'exec', 'eval', 'compile', 'open', 'input', 'file',
            'reduce', 'map', 'filter', 'vars', 'dir'
        ]
        
        for keyword in dangerous_keywords:
            if keyword in input_data.lower():
                logger.warning(f"Job {job_id}: Python dangerous keyword detected: {keyword}")
                return False, f"Input contains forbidden keyword: {keyword}"
        
        return True, None
    
    def _validate_javascript_input(self, job_id: str, input_data: str) -> Tuple[bool, Optional[str]]:
        """JavaScript-specific input validation"""
        dangerous_keywords = [
            'require', 'import', 'eval', 'function', 'constructor',
            'prototype', '__proto__', 'child_process', 'fs', 'os',
            'process', 'global'
        ]
        
        for keyword in dangerous_keywords:
            if keyword in input_data.lower():
                logger.warning(f"Job {job_id}: JavaScript dangerous keyword detected: {keyword}")
                return False, f"Input contains forbidden keyword: {keyword}"
        
        return True, None
    
    def _validate_c_input(self, job_id: str, input_data: str) -> Tuple[bool, Optional[str]]:
        """C/C++-specific input validation"""
        dangerous_keywords = [
            'system', 'exec', 'popen', 'fork', 'dlopen', 'elf',
            'mprotect', 'mmap', 'signal', 'setuid', 'seteuid'
        ]
        
        for keyword in dangerous_keywords:
            if keyword in input_data.lower():
                logger.warning(f"Job {job_id}: C dangerous keyword detected: {keyword}")
                return False, f"Input contains forbidden keyword: {keyword}"
        
        return True, None

