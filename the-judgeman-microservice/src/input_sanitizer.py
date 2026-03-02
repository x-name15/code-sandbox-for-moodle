"""
JudgeMan Microservice - Input Sanitizer
Sanitizes input to prevent escaping shells, breaking syntax, etc.
"""
import logging
import re
from typing import Optional

logger = logging.getLogger(__name__)


class InputSanitizer:
    """Sanitizes input data for safe processing"""
    
    # Characters that could break out of context
    POTENTIALLY_DANGEROUS_CHARS = {
        "'": "Single quote",
        '"': "Double quote",
        "`": "Backtick",
        "\\": "Backslash",
        "$": "Dollar sign",
        "|": "Pipe",
        "&": "Ampersand",
        ";": "Semicolon",
        "<": "Less than",
        ">": "Greater than",
        "\n": "Newline (unexpected)",
        "\r": "Carriage return (unexpected)",
    }
    
    @staticmethod
    def escape_for_shell(data: str) -> str:
        """
        Properly escape data for shell commands
        Returns data safe for use in shell single quotes
        
        In shell, it's typically safest to wrap in single quotes
        Only need to handle single quotes specially
        """
        # Replace single quotes with '\'' (end quote, escaped quote, start quote)
        return data.replace("'", "'\\''")
    
    @staticmethod
    def escape_for_json(data: str) -> str:
        """Escape data for JSON context"""
        # JSON escaping
        data = data.replace("\\", "\\\\")
        data = data.replace('"', '\\"')
        data = data.replace("\b", "\\b")
        data = data.replace("\f", "\\f")
        data = data.replace("\n", "\\n")
        data = data.replace("\r", "\\r")
        data = data.replace("\t", "\\t")
        return data
    
    @staticmethod
    def escape_for_csv(data: str) -> str:
        """Escape data for CSV context"""
        if '"' in data or ',' in data or '\n' in data:
            # Quote and escape inner quotes
            return '"' + data.replace('"', '""') + '"'
        return data
    
    @staticmethod
    def remove_null_bytes(data: str) -> str:
        """Remove null bytes that could confuse systems"""
        return data.replace('\x00', '')
    
    @staticmethod
    def normalize_newlines(data: str) -> str:
        """
        Normalize newlines to \n only
        Converts \r\n and \r to \n
        """
        data = data.replace('\r\n', '\n')
        data = data.replace('\r', '\n')
        return data
    
    @staticmethod
    def sanitize_for_safe_input(data: str) -> str:
        """
        General-purpose sanitization for user input
        - Remove null bytes
        - Normalize newlines
        - Keep data as-is (no escaping, just safety checks)
        """
        data = InputSanitizer.remove_null_bytes(data)
        data = InputSanitizer.normalize_newlines(data)
        return data
    
    @staticmethod
    def check_for_suspicious_patterns(data: str, job_id: Optional[str] = None) -> dict:
        """
        Check for patterns that might indicate attempts to escape context
        
        Returns:
            Dictionary with findings
        """
        report = {
            'suspicious_patterns': [],
            'potentially_dangerous_chars': []
        }
        
        # Check for potential shell escaping patterns
        escape_patterns = [
            (r"'\\?'", "Escaped quote pattern"),
            (r'\$\{', "Variable expansion"),
            (r'\$\(', "Command substitution"),
            (r'\\x[0-9a-fA-F]{2}', "Hex escape sequence"),
            (r'\\[0-7]{3}', "Octal escape sequence"),
        ]
        
        for pattern, description in escape_patterns:
            if re.search(pattern, data):
                report['suspicious_patterns'].append({
                    'pattern': pattern,
                    'description': description,
                    'matches': re.findall(pattern, data)[:3]  # First 3 matches
                })
                if job_id:
                    logger.warning(f"Job {job_id}: {description} detected")
        
        # Check for dangerous characters outside of normal input
        for char, description in InputSanitizer.POTENTIALLY_DANGEROUS_CHARS.items():
            if char in data and char not in ('\n', '\t'):  # Allow newlines and tabs
                # Count occurrences
                count = data.count(char)
                if count > 10:  # Alert if many occurrences
                    report['potentially_dangerous_chars'].append({
                        'char': repr(char),
                        'description': description,
                        'count': count
                    })
                    if job_id:
                        logger.warning(
                            f"Job {job_id}: {description} ({count} occurrences) detected"
                        )
        
        return report
    
    @staticmethod
    def get_safe_representation(data: str, max_chars: int = 100) -> str:
        """
        Get a safe string representation of data for logging/display
        Escapes all special characters for visibility
        """
        # Escape special characters for display
        safe = data
        safe = safe.replace('\\', '\\\\')
        safe = safe.replace('\n', '\\n')
        safe = safe.replace('\r', '\\r')
        safe = safe.replace('\t', '\\t')
        safe = safe.replace('"', '\\"')
        safe = safe.replace("'", "\\'")
        
        # Truncate if needed
        if len(safe) > max_chars:
            safe = safe[:max_chars] + f"... (+{len(safe) - max_chars} chars)"
        
        return safe
