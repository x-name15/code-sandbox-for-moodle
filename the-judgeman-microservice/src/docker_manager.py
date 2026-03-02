"""
JudgeMan Microservice - Docker Manager Module
Handles Docker container creation, execution, and cleanup
"""
import os
import uuid
import shutil
import logging
import tempfile
from typing import Dict, Tuple, Optional
from pathlib import Path

import docker
from docker.models.containers import Container

from .config import Config

logger = logging.getLogger(__name__)


class DockerManager:
    """Manages Docker sandbox containers for code execution"""
    
    def __init__(self, config: Config):
        self.config = config
        self.client = docker.from_env()
        self._cleanup_on_init()
        logger.info("Docker client initialized")
    
    def _cleanup_on_init(self) -> None:
        """Clean up any leftover sandbox containers from previous runs"""
        try:
            containers = self.client.containers.list(all=True)
            for container in containers:
                # Only cleanup containers that look like our sandbox containers
                if container.name and container.name.startswith(('sandbox_', 'job_')):
                    try:
                        container.remove(force=True)
                        logger.debug(f"Cleaned up leftover container: {container.name}")
                    except Exception:
                        pass
        except Exception as e:
            logger.debug(f"Cleanup on init skipped: {e}")
    
    def create_job_workspace(self, job_id: str, code: str, filename: str, input_data: Optional[str] = None) -> str:
        """
        Create a workspace directory for the job and write the code file
        
        Args:
            job_id: Job identifier
            code: Code to execute
            filename: Filename for the code
            input_data: Optional input data for the program
        
        Returns:
            Path to the job workspace directory
        """
        if self.config.is_running_in_docker():
            # Running in Docker: use shared volume
            job_dir_name = f"job_{job_id}_{uuid.uuid4().hex[:8]}"
            job_work_dir = os.path.join(self.config.SHARED_MOUNT_PATH, job_dir_name)
            os.makedirs(job_work_dir, exist_ok=True)
            logger.debug(f"Job {job_id}: Created workspace in shared volume: {job_work_dir}")
        else:
            # Development mode: use temp directory
            job_work_dir = tempfile.mkdtemp(prefix=f"local_job_{job_id}_")
            logger.debug(f"Job {job_id}: Created workspace in local temp: {job_work_dir}")
        
        # Write code to file
        file_path = os.path.join(job_work_dir, filename)
        with open(file_path, "w", encoding="utf-8") as f:
            f.write(code)
        
        # Write input data to file (create even if empty to avoid "file not found" errors)
        input_file = os.path.join(job_work_dir, "input.txt")
        with open(input_file, "w", encoding="utf-8") as f:
            if input_data:
                f.write(input_data)
                logger.info(f"Job {job_id}: Input file created with {len(input_data)} bytes")
            else:
                logger.debug(f"Job {job_id}: Input file created (empty)")
        
        logger.info(f"Job {job_id}: Workspace ready at {job_work_dir}")
        return job_work_dir
    
    def run_sandbox(
        self, 
        job_id: str, 
        workspace_path: str, 
        image: str, 
        command: str,
        input_data: Optional[str] = None
    ) -> Tuple[int, str]:
        """
        Run code in an isolated Docker sandbox
        
        Args:
            job_id: Job identifier
            workspace_path: Path to workspace with code
            image: Docker image to use
            command: Command to execute
            input_data: Optional input data (will be provided via stdin redirection)
        
        Returns:
            Tuple of (exit_code, logs)
        """
        container: Optional[Container] = None
        exit_code = 1
        logs = ""
        
        logger.debug(f"Job {job_id}: run_sandbox() called with input_data length={len(input_data) if input_data else 0}")
        if input_data:
            logger.debug(f"Job {job_id}: Input data preview: {repr(input_data[:200])}")
        
        try:
            # Determine volume configuration
            if self.config.is_running_in_docker():
                job_dir_name = os.path.basename(workspace_path)
                volumes = {
                    self.config.SHARED_VOLUME_NAME: {
                        'bind': '/shared_data',
                        'mode': 'rw'
                    }
                }
                jail_path = f"/shared_data/{job_dir_name}"
                final_command = command.replace('/jail', jail_path)
            else:
                # In development: use read-write if input exists (to read input.txt)
                volume_mode = 'rw' if input_data else 'ro'
                volumes = {
                    workspace_path: {'bind': '/jail', 'mode': volume_mode}
                }
                jail_path = "/jail"
                final_command = command
            
            # Build command with proper stdin handling
            logger.debug(f"Job {job_id}: Base command: {final_command}")
            logger.debug(f"Job {job_id}: Input data present: {bool(input_data)}")
            
            # ALWAYS pipe input.txt to stdin - this ensures programs expecting input()
            # won't hang or crash with EOFError. If input.txt is empty, it just sends EOF.
            final_command = f"cat {jail_path}/input.txt | ( {final_command} )"
            logger.debug(f"Job {job_id}: Command with input pipe: {final_command}")
            
            logger.info(f"Job {job_id}: Starting sandbox container (image={image})")
            
            # Create and run container (without detach, to properly handle stdin)
            container = self.client.containers.run(
                image=image,
                command=f"sh -c '{final_command}'",
                volumes=volumes,
                working_dir=jail_path,
                # Security settings
                network_disabled=True,
                mem_limit=self.config.MEMORY_LIMIT,
                pids_limit=self.config.PIDS_LIMIT,
                cpu_quota=self.config.CPU_QUOTA,
                user="nobody",
                read_only=False,
                tmpfs={'/tmp': 'size=64m,exec'},
                detach=True,  # Run in background
                remove=False   # Manual cleanup for better control
            )
            
            # Wait for execution with timeout
            try:
                result = container.wait(timeout=self.config.TIMEOUT_SECONDS)
                exit_code = result['StatusCode']
                logs = container.logs().decode('utf-8', errors='replace')
                logger.info(f"Job {job_id}: Container exited with code {exit_code}")
            except Exception as timeout_err:
                logger.warning(f"Job {job_id}: Timeout exceeded ({self.config.TIMEOUT_SECONDS}s)")
                logs = "Execution Timed Out."
                exit_code = 124  # Standard timeout exit code
                try:
                    container.kill()
                except Exception as kill_err:
                    logger.debug(f"Job {job_id}: Failed to kill container: {kill_err}")
        
        except docker.errors.ImageNotFound:
            logger.error(f"Job {job_id}: Docker image not found: {image}")
            logs = f"System Error: Docker image '{image}' not found"
            exit_code = 125
        except docker.errors.APIError as api_err:
            logger.error(f"Job {job_id}: Docker API error: {api_err}")
            logs = f"System Error: Docker API error - {str(api_err)}"
            exit_code = 125
        except Exception as e:
            logger.error(f"Job {job_id}: Unexpected error in sandbox: {e}", exc_info=True)
            logs = f"System Error: {str(e)}"
            exit_code = 1
        finally:
            # Cleanup container
            if container:
                try:
                    container.remove(force=True)
                    logger.debug(f"Job {job_id}: Container removed successfully")
                except Exception as rm_err:
                    logger.warning(f"Job {job_id}: Failed to remove container: {rm_err}")
        
        return exit_code, logs
    
    def cleanup_workspace(self, job_id: str, workspace_path: str) -> None:
        """Clean up job workspace directory"""
        try:
            if os.path.exists(workspace_path):
                shutil.rmtree(workspace_path, ignore_errors=True)
                logger.debug(f"Job {job_id}: Workspace cleaned: {workspace_path}")
        except Exception as e:
            logger.warning(f"Job {job_id}: Failed to clean workspace: {e}")
    
    def cleanup(self) -> None:
        """Cleanup Docker client resources"""
        try:
            self.client.close()
            logger.info("Docker client closed")
        except Exception as e:
            logger.warning(f"Error closing Docker client: {e}")
