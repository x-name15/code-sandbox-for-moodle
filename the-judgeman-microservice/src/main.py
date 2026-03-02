"""
JudgeMan Microservice - Main Entry Point
Code Sandbox Worker for secure code execution
"""
import sys
import signal
import logging
from typing import Optional

from .config import get_config, setup_logging
from .worker import JobWorker

logger = logging.getLogger(__name__)

# Global worker instance for signal handling
worker_instance: Optional[JobWorker] = None


def signal_handler(signum: int, frame) -> None:
    """Handle termination signals gracefully"""
    signal_name = signal.Signals(signum).name
    logger.info(f"Received signal {signal_name}. Shutting down gracefully...")
    
    if worker_instance:
        worker_instance.stop()
    
    sys.exit(0)


def main() -> None:
    """Main application entry point"""
    global worker_instance
    
    # Load configuration
    config = get_config()
    
    # Setup logging
    setup_logging(config)
    
    # Print banner
    print_banner(config)
    
    # Register signal handlers
    signal.signal(signal.SIGINT, signal_handler)
    signal.signal(signal.SIGTERM, signal_handler)
    
    try:
        # Create and start worker
        worker_instance = JobWorker(config)
        worker_instance.start()
    
    except KeyboardInterrupt:
        logger.info("Interrupted by user")
    except Exception as e:
        logger.critical(f"Fatal error in main: {e}", exc_info=True)
        sys.exit(1)
    finally:
        if worker_instance:
            worker_instance.stop()


def print_banner(config) -> None:
    """Print application banner"""
    banner = """
╔═══════════════════════════════════════════════════════════════╗
║                    THE JUDGEMAN MICROSERVICE                  ║
║                   Secure Code Sandbox Worker                  ║
╚═══════════════════════════════════════════════════════════════╝
    """
    print(banner)
    logger.info(f"Version: 2.0.0")
    logger.info(f"Environment: {config.ENVIRONMENT}")
    logger.info(f"Queue: {config.RABBIT_QUEUE_NAME}")
    logger.info(f"Supported Languages: {', '.join(config.languages.keys())}")
    print()


if __name__ == '__main__':
    main()
