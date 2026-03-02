"""
JudgeMan Microservice - Configuration Module
Handles environment variables and configuration loading
"""
import os
import json
import logging
from typing import Dict, Any, Optional
from pathlib import Path
from dotenv import load_dotenv

logger = logging.getLogger(__name__)


class Config:
    """Application configuration manager"""
    
    def __init__(self, env_file: Optional[str] = None):
        """Initialize configuration from .env and config.json"""
        # Load .env file
        if env_file:
            load_dotenv(env_file)
        else:
            load_dotenv()  # Load from .env in current directory
        
        # RabbitMQ Settings
        self.RABBIT_HOST = os.getenv('RABBIT_HOST', 'rabbitmq')
        self.RABBIT_PORT = int(os.getenv('RABBIT_PORT', '5672'))
        self.RABBIT_USER = os.getenv('RABBIT_USER', 'guest')
        self.RABBIT_PASS = os.getenv('RABBIT_PASS', 'guest')
        self.RABBIT_QUEUE_NAME = os.getenv('RABBIT_QUEUE_NAME', 'moodle_code_jobs')
        self.RABBIT_PREFETCH_COUNT = int(os.getenv('RABBIT_PREFETCH_COUNT', '1'))
        self.RABBIT_HEARTBEAT = int(os.getenv('RABBIT_HEARTBEAT', '600'))
        
        # Docker Settings
        self.SHARED_VOLUME_NAME = os.getenv('SHARED_VOLUME_NAME', 'judgeman_data')
        self.SHARED_MOUNT_PATH = os.getenv('SHARED_MOUNT_PATH', '/shared_data')
        
        # Resource Limits
        self.MEMORY_LIMIT = os.getenv('MEMORY_LIMIT', '128m')
        self.CPU_QUOTA = int(os.getenv('CPU_QUOTA', '50000'))
        self.PIDS_LIMIT = int(os.getenv('PIDS_LIMIT', '50'))
        self.TIMEOUT_SECONDS = int(os.getenv('TIMEOUT_SECONDS', '5'))
        self.MAX_OUTPUT_SIZE = int(os.getenv('MAX_OUTPUT_SIZE', '10000'))
        
        # Input Security Settings
        self.MAX_INPUT_SIZE = int(os.getenv('MAX_INPUT_SIZE', '1000000'))  # 1MB
        self.MAX_INPUT_LINES = int(os.getenv('MAX_INPUT_LINES', '10000'))
        self.INPUT_TIMEOUT_SECONDS = int(os.getenv('INPUT_TIMEOUT_SECONDS', '1'))
        
        # Application Settings
        self.CONFIG_PATH = os.getenv('CONFIG_PATH', 'config.json')
        self.LOG_LEVEL = os.getenv('LOG_LEVEL', 'INFO')
        self.ENVIRONMENT = os.getenv('ENVIRONMENT', 'development')
        
        # Load language configurations
        self.languages = self._load_language_config()
    
    def _load_language_config(self) -> Dict[str, Any]:
        """Load language configurations from JSON file"""
        try:
            config_path = Path(self.CONFIG_PATH)
            if not config_path.exists():
                logger.warning(f"Config file {self.CONFIG_PATH} not found, using defaults")
                return self._get_default_languages()
            
            with open(config_path, 'r') as f:
                config_data = json.load(f)
                return config_data.get('languages', self._get_default_languages())
        except Exception as e:
            logger.error(f"Failed to load language config: {e}")
            return self._get_default_languages()
    
    @staticmethod
    def _get_default_languages() -> Dict[str, Any]:
        """Default language configurations"""
        return {
            "python": {
                "image": "python:3.9-alpine",
                "filename": "main.py",
                "command": "python /jail/main.py"
            },
            "javascript": {
                "image": "node:18-alpine",
                "filename": "main.js",
                "command": "node /jail/main.js"
            },
            "cpp": {
                "image": "frolvlad/alpine-gxx",
                "filename": "main.cpp",
                "command": "g++ -o /tmp/out /jail/main.cpp && /tmp/out"
            }
        }
    
    def get_language_config(self, language: str) -> Optional[Dict[str, Any]]:
        """Get configuration for a specific language"""
        return self.languages.get(language)
    
    def is_running_in_docker(self) -> bool:
        """Check if the application is running inside Docker"""
        return os.path.exists('/.dockerenv') or os.path.isfile('/proc/self/cgroup')
    
    def __repr__(self) -> str:
        return f"<Config env={self.ENVIRONMENT} queue={self.RABBIT_QUEUE_NAME}>"


# Global config instance
_config: Optional[Config] = None


def get_config() -> Config:
    """Get or create global configuration instance"""
    global _config
    if _config is None:
        _config = Config()
    return _config


def setup_logging(config: Config) -> None:
    """Setup application-wide logging"""
    log_level = getattr(logging, config.LOG_LEVEL.upper(), logging.INFO)
    
    logging.basicConfig(
        level=log_level,
        format='[%(asctime)s] [%(name)s] [%(levelname)s] %(message)s',
        datefmt='%Y-%m-%d %H:%M:%S'
    )
    
    # Reduce verbosity of pika logger
    logging.getLogger('pika').setLevel(logging.WARNING)
    
    logger.info(f"Logging configured: level={config.LOG_LEVEL}, env={config.ENVIRONMENT}")
