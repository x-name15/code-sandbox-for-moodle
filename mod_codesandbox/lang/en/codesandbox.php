<?php
$string['pluginname'] = 'Code Sandbox';
$string['modulename'] = 'Code Sandbox';
$string['modulenameplural'] = 'Code Sandboxes';
$string['modulename_help'] = 'An interactive and secure code execution environment using Docker and RabbitMQ.';
$string['codesandboxname'] = 'Sandbox Name';
$string['pluginadministration'] = 'Code Sandbox Administration';

// Teacher Navigation and View
$string['teacher_view_submissions'] = 'View Student Submissions';
$string['submissions_header'] = 'Received Submissions';
$string['no_submissions'] = 'No submissions have been sent yet.';
$string['btn_back_editor'] = 'Back to Editor';

// IDE Interface
$string['status_not_submitted'] = 'Not submitted';
$string['status_submitted'] = 'Submitted';
$string['btn_run_label'] = 'Test';
$string['btn_run_title'] = 'Run code in terminal';
$string['btn_submit_label'] = 'Submit';
$string['btn_submit_title'] = 'Send assignment to teacher';
$string['terminal_header'] = 'CONSOLE / OUTPUT';
$string['console_inputs'] = 'Inputs';
$string['console_inputs_placeholder'] = 'Enter inputs here (one per line)';
$string['console_inputs_help'] = 'Provide inputs for your code (one value per line).';
$string['btn_clear'] = 'Clear';
$string['terminal_cleaned'] = '> Cleared.';
$string['console_ready'] = '> Ready to code in <strong>{$a}</strong>.';

// Table Columns
$string['table_student'] = 'Student';
$string['table_date'] = 'Submission Date';
$string['table_actions'] = 'Actions';
$string['btn_review'] = 'Review Code';
$string['btn_cancel'] = 'Cancel';

// JS Messages and Constraints (Corrected with actual line break)
$string['msg_confirm_submit'] = "Once submitted, you will not be able to make further changes.\nAre you sure you want to submit your assignment?";
$string['msg_submitted_success'] = 'Assignment submitted successfully';
$string['msg_running'] = 'Running code...';
$string['msg_sending'] = 'Submitting assignment...';
$string['msg_err_server'] = 'Server error. Please try again.';
$string['msg_err_conn'] = 'Connection error:';
$string['already_submitted'] = 'You have already made a final submission. The editor is in read-only mode.';
$string['deadline_passed'] = 'The submission deadline has passed.';

// Form Settings
$string['sandbox_settings'] = 'Sandbox Settings';
$string['maxgrade'] = 'Maximum grade';
$string['maxgrade_help'] = 'Set the maximum grade for this activity. This value will be used in the gradebook.';
$string['duedate'] = 'Due date';
$string['duedate_info'] = 'Due: {$a}';
$string['no_duedate'] = 'No due date set';
$string['instructions'] = 'Instructions';
$string['instructions_help'] = 'Write here in detail what the student should achieve.';
$string['select_language'] = 'Programming language';
$string['select_language_placeholder'] = 'Select a language...';

// Errors
$string['error_noinstructions'] = 'You must provide the exercise instructions.';
$string['error_nodefaultlang'] = 'You must select a programming language.';
$string['error_nograde'] = 'You must define a maximum grade.';

// Grading
$string['grade_submission'] = 'Grade Submission: {$a}';
$string['grade_limit'] = 'Grade (0-{$a})';
$string['grade_header'] = 'Grade';
$string['teacher_comment'] = 'Teacher Comments';
$string['save_grade'] = 'Save Grade';
$string['grade_saved'] = 'The grade has been saved successfully';
$string['student_code'] = 'Student Code';
$string['output_results'] = 'Execution Results';
$string['no_code_submitted'] = '// No code available';
$string['no_output_recorded'] = '> No output recorded';

// Capabilities (permissions)
$string['codesandbox:view'] = 'View Code Sandbox';
$string['codesandbox:viewreport'] = 'View submission reports';
$string['codesandbox:grade'] = 'Grade submissions';

// RabbitMQ Settings
$string['rabbitmq_settings'] = 'RabbitMQ Configuration';
$string['rabbitmq_settings_desc'] = 'Connection parameters for the message queue server';
$string['rabbitmq_host'] = 'RabbitMQ Host';
$string['rabbitmq_port'] = 'RabbitMQ Port';
$string['rabbitmq_user'] = 'RabbitMQ User';
$string['rabbitmq_password'] = 'RabbitMQ Password';

// Worker Settings
$string['worker_settings'] = 'Worker Configuration';
$string['worker_timeout'] = 'Execution Timeout (seconds)';
$string['rabbitmq_queue'] = 'RabbitMQ Queue Name';

// Queue Messages
$string['queue_unavailable'] = 'The execution service is temporarily unavailable. Please try again later.';
$string['job_queued'] = 'Your code has been queued for execution.';

// Polling/Async Messages
$string['msg_job_queued'] = 'Job queued: {$a}...';
$string['msg_waiting_results'] = 'Waiting for results...';
$string['msg_timeout'] = 'TIMEOUT: The job took too long. Check back later.';
$string['msg_no_output'] = '(No output)';
$string['msg_stderr_label'] = '[STDERR]';
$string['msg_exitcode_label'] = '[Exit Code: {$a}]';

// Notifications
$string['notification_graded_subject'] = 'Your assignment "{$a}" has been graded';
$string['notification_graded_message'] = 'Your activity has been graded with: {$a->grade} / {$a->maxgrade}. Teacher comments: {$a->comment}';
$string['notification_graded_message_html'] = '<p>Your activity <strong>{$a->activityname}</strong> has been graded.</p><p>Grade: <strong>{$a->grade} / {$a->maxgrade}</strong></p><p>Comment:</p><blockquote>{$a->comment}</blockquote>';
$string['notification_graded_small'] = 'Your activity {$a} has been graded.';

$string['notification_assignment_subject'] = 'New assignment: {$a}';
$string['notification_assignment_message'] = 'A new Code Sandbox has been assigned.';
$string['notification_completed_subject'] = 'Submission received in {$a}';
$string['notification_completed_message'] = '{$a} has completed their sandbox.';
$string['notification_updated_subject'] = 'Updated: {$a}';
$string['notification_updated_message'] = 'The activity has been updated. Changes: {$a}';
$string['notification_change_duedate'] = 'due date';
$string['notification_change_instructions'] = 'instructions';
$string['notification_change_grade'] = 'maximum grade';
$string['notification_change_general'] = 'general settings';

// Autograding
$string['autograding_header'] = 'Automatic Grading (Optional)';
$string['autograding_enable'] = 'Enable Autograding';
$string['autograding_enable_desc'] = 'Compare output with test cases';
$string['testcases'] = 'Test Cases (JSON)';
$string['testcases_help'] = 'Define test cases in JSON format. Example: [{"input": "2\\n2", "output": "4"}]';
$string['autograding_results'] = 'Autograding Results';
$string['tests_passed'] = 'Tests Passed: {$a}';
$string['auto_graded'] = 'Automatically graded';
$string['test_case_passed'] = 'Passed';
$string['test_case_failed'] = 'Failed';
$string['expected_output'] = 'Expected Output';
$string['actual_output'] = 'Actual Output';