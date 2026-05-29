<?php

return [
    'working_directory' => env('COMMAND_RUNNER_WORKING_DIRECTORY', base_path('..')),

    'queue' => env('COMMAND_RUNNER_QUEUE', 'cli-commands'),

    /*
    |--------------------------------------------------------------------------
    | Command Runner Registry
    |--------------------------------------------------------------------------
    |
    | These are the only workflow commands the WebUI may launch. Each command
    | is rendered as a token array before Symfony Process receives it, avoiding
    | shell-string concatenation.
    |
    */

    'commands' => [
        'signal.run' => [
            'label' => 'Continue Signal7 task',
            'description' => 'Run the Signal7 orchestrator for a task or goal.',
            'system' => 'signal7',
            'tokens' => ['claude', '/signal'],
            'requires_task' => false,
            'timeout' => 600,
            'arguments' => [
                [
                    'name' => 'task_id',
                    'label' => 'Task ID',
                    'type' => 'hidden',
                    'required' => false,
                    'default_from' => 'task.task_id',
                    'placement' => 'positional',
                ],
                [
                    'name' => 'input',
                    'label' => 'Input',
                    'type' => 'textarea',
                    'required' => false,
                    'placement' => 'positional',
                ],
            ],
        ],
        'signal.approve' => [
            'label' => 'Approve Signal7 gate',
            'description' => 'Approve the current gate for a Signal7 task.',
            'system' => 'signal7',
            'tokens' => ['claude', '/signal'],
            'requires_task' => true,
            'timeout' => 600,
            'arguments' => [
                [
                    'name' => 'task_id',
                    'label' => 'Task ID',
                    'type' => 'hidden',
                    'required' => true,
                    'default_from' => 'task.task_id',
                    'placement' => 'positional',
                ],
                [
                    'name' => 'response',
                    'label' => 'Response',
                    'type' => 'hidden',
                    'required' => true,
                    'default' => 'continue',
                    'placement' => 'positional',
                ],
                [
                    'name' => 'approver',
                    'label' => 'Approver',
                    'type' => 'hidden',
                    'required' => false,
                    'placement' => 'option',
                    'option' => '--approver',
                ],
            ],
        ],
        'signal.publish' => [
            'label' => 'Publish Signal7 task',
            'description' => 'Run the publish phase for a Signal7 task.',
            'system' => 'signal7',
            'tokens' => ['claude', '/signal'],
            'requires_task' => true,
            'timeout' => 600,
            'arguments' => [
                [
                    'name' => 'task_id',
                    'label' => 'Task ID',
                    'type' => 'hidden',
                    'required' => true,
                    'default_from' => 'task.task_id',
                    'placement' => 'positional',
                ],
                [
                    'name' => 'input',
                    'label' => 'Input',
                    'type' => 'hidden',
                    'required' => true,
                    'default' => 'publish',
                    'placement' => 'positional',
                ],
                [
                    'name' => 'assets',
                    'label' => 'Asset IDs',
                    'type' => 'hidden',
                    'required' => false,
                    'placement' => 'option',
                    'option' => '--assets',
                ],
            ],
        ],
        'signal-task.list' => [
            'label' => 'List Signal7 tasks',
            'description' => 'List active Signal7 tasks.',
            'system' => 'signal7',
            'tokens' => ['claude', '/signal-task', 'list'],
            'requires_task' => false,
            'timeout' => 120,
            'arguments' => [],
        ],
        'signal-task.status' => [
            'label' => 'Signal7 task status',
            'description' => 'Show status for a Signal7 task.',
            'system' => 'signal7',
            'tokens' => ['claude', '/signal-task', 'status'],
            'requires_task' => false,
            'timeout' => 120,
            'arguments' => [
                [
                    'name' => 'task_id',
                    'label' => 'Task ID',
                    'type' => 'text',
                    'required' => true,
                    'placement' => 'positional',
                ],
            ],
        ],
        'signal-task.cancel' => [
            'label' => 'Cancel Signal7 task',
            'description' => 'Cancel a Signal7 task with a reason.',
            'system' => 'signal7',
            'tokens' => ['claude', '/signal-task', 'cancel'],
            'requires_task' => false,
            'timeout' => 120,
            'arguments' => [
                ['name' => 'task_id', 'label' => 'Task ID', 'type' => 'text', 'required' => true, 'placement' => 'positional'],
                ['name' => 'reason', 'label' => 'Reason', 'type' => 'textarea', 'required' => true, 'placement' => 'option', 'option' => '--reason'],
            ],
        ],
        'signal-task.defer' => [
            'label' => 'Defer Signal7 task',
            'description' => 'Park a Signal7 task for later.',
            'system' => 'signal7',
            'tokens' => ['claude', '/signal-task', 'defer'],
            'requires_task' => false,
            'timeout' => 120,
            'arguments' => [
                ['name' => 'task_id', 'label' => 'Task ID', 'type' => 'text', 'required' => true, 'placement' => 'positional'],
            ],
        ],
        'signal-backlog.promote' => [
            'label' => 'Promote Signal7 backlog item',
            'description' => 'Promote a Signal7 backlog item into a task.',
            'system' => 'signal7',
            'tokens' => ['claude', '/signal-backlog', 'promote'],
            'requires_task' => false,
            'timeout' => 180,
            'arguments' => [
                ['name' => 'entry_id', 'label' => 'Backlog ID', 'type' => 'text', 'required' => true, 'placement' => 'positional'],
            ],
        ],
        'signal-recipe.run' => [
            'label' => 'Run Signal7 recipe',
            'description' => 'Run a Signal7 recipe by name.',
            'system' => 'signal7',
            'tokens' => ['claude', '/signal-recipe', 'run'],
            'requires_task' => false,
            'timeout' => 600,
            'arguments' => [
                ['name' => 'recipe', 'label' => 'Recipe', 'type' => 'text', 'required' => true, 'placement' => 'positional'],
            ],
        ],
        'hyper.run' => [
            'label' => 'Continue Hyper7 task',
            'description' => 'Run the Hyper7 orchestrator for a task or goal.',
            'system' => 'hyper7',
            'tokens' => ['claude', '/hyper'],
            'requires_task' => false,
            'timeout' => 600,
            'arguments' => [
                [
                    'name' => 'task_id',
                    'label' => 'Task ID',
                    'type' => 'hidden',
                    'required' => false,
                    'default_from' => 'task.task_id',
                    'placement' => 'positional',
                ],
                ['name' => 'input', 'label' => 'Input', 'type' => 'textarea', 'required' => false, 'placement' => 'positional'],
            ],
        ],
        'hyper.approve' => [
            'label' => 'Approve Hyper7 gate',
            'description' => 'Approve the current gate for a Hyper7 task.',
            'system' => 'hyper7',
            'tokens' => ['claude', '/hyper'],
            'requires_task' => true,
            'timeout' => 600,
            'arguments' => [
                ['name' => 'task_id', 'label' => 'Task ID', 'type' => 'hidden', 'required' => true, 'default_from' => 'task.task_id', 'placement' => 'positional'],
                ['name' => 'response', 'label' => 'Response', 'type' => 'hidden', 'required' => true, 'default' => 'continue', 'placement' => 'positional'],
                ['name' => 'approver', 'label' => 'Approver', 'type' => 'hidden', 'required' => false, 'placement' => 'option', 'option' => '--approver'],
            ],
        ],
        'hyper-task.list' => [
            'label' => 'List Hyper7 tasks',
            'description' => 'List active Hyper7 tasks.',
            'system' => 'hyper7',
            'tokens' => ['claude', '/hyper-task', 'list'],
            'requires_task' => false,
            'timeout' => 120,
            'arguments' => [],
        ],
        'hyper-task.status' => [
            'label' => 'Hyper7 task status',
            'description' => 'Show status for a Hyper7 task.',
            'system' => 'hyper7',
            'tokens' => ['claude', '/hyper-task', 'status'],
            'requires_task' => false,
            'timeout' => 120,
            'arguments' => [
                ['name' => 'task_id', 'label' => 'Task ID', 'type' => 'text', 'required' => true, 'placement' => 'positional'],
            ],
        ],
        'hyper-backlog.promote' => [
            'label' => 'Promote Hyper7 backlog item',
            'description' => 'Promote a Hyper7 backlog item into a task.',
            'system' => 'hyper7',
            'tokens' => ['claude', '/hyper-backlog', 'promote'],
            'requires_task' => false,
            'timeout' => 180,
            'arguments' => [
                ['name' => 'entry_id', 'label' => 'Backlog ID', 'type' => 'text', 'required' => true, 'placement' => 'positional'],
            ],
        ],
    ],
];
