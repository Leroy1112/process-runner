<?php

declare(strict_types=1);

namespace Nusje2000\ProcessRunner\Listener;

use Nusje2000\ProcessRunner\Task;
use Nusje2000\ProcessRunner\TaskList;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

/**
 * Version of the ConsoleListener which does not update contents in the console but only writes status changes.
 */
final class StaticConsoleListener implements ExecutionListener
{
    /**
     * @var ConsoleOutputInterface
     */
    private $output;

    /**
     * @var int
     */
    private $priority;

    /**
     * @var array<int, array<TaskList|array<int, array<string, bool>>>>
     */
    private $taskLists;

    /**
     * @param bool $configureFormatter If true, the formatter will be configured with default output styling.
     */
    public function __construct(ConsoleOutputInterface $output, bool $configureFormatter = true, int $priority = 0)
    {
        $this->output = $output;
        $this->priority = $priority;

        if ($configureFormatter) {
            $outputFormatter = $this->output->getFormatter();
            $outputFormatter->setStyle('error', new OutputFormatterStyle('red'));
            $outputFormatter->setStyle('success', new OutputFormatterStyle('green'));
            $outputFormatter->setStyle('idle', new OutputFormatterStyle('blue'));
            $outputFormatter->setStyle('running', new OutputFormatterStyle('yellow'));
        }
    }

    public function onTick(TaskList $taskList): void
    {
        $updates = $this->getChanges($taskList);

        $buffer = [];
        foreach ($updates->getIterator() as $update) {
            if ($update->isRunning()) {
                $buffer[] = sprintf('%s is <running>running</running>', $update->getName());
            }

            if ($update->isFailed()) {
                $buffer[] = sprintf('%s has <error>failed</error>', $update->getName());
                $output = $update->getProcess()->getOutput();
                if ('' !== $output) {
                    $buffer[] = 'Output:';
                    $buffer[] = $this->prepareProcessOutput($output);
                    $buffer[] = '';
                }

                $output = $update->getProcess()->getErrorOutput();
                if ('' !== $output) {
                    $buffer[] = 'Error output:';
                    $buffer[] = $this->prepareProcessOutput($output);
                    $buffer[] = '';
                }
            }

            if ($update->isSuccessfull()) {
                $buffer[] = sprintf('%s is <success>successfull</success>', $update->getName());
            }
        }

        $this->output->writeln($buffer);

        $this->setPreviousState($taskList);
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    private function getChanges(TaskList $taskList): TaskList
    {
        $previous = $this->getPreviousState($taskList);

        $changes = [];
        foreach ($taskList->getIterator() as $index => $task) {
            if (!isset($previous[$index])) {
                $changes[$index] = $task;

                continue;
            }

            /** @var array<bool> $previousTask */
            $previousTask = $previous[$index];

            if ($previousTask['completed'] !== $task->isCompleted() || $previousTask['running'] !== $task->isRunning()) {
                $changes[] = $task;
            }
        }

        return new TaskList($changes);
    }

    /**
     * @return array<int, array<string, bool>>
     */
    private function getPreviousState(TaskList $taskList): array
    {
        $index = spl_object_id($taskList);

        /** @var array<int, array<string, bool>> $previous */
        $previous = $this->taskLists[$index]['previous'] ?? [];

        return $previous;
    }

    private function setPreviousState(TaskList $taskList): void
    {
        $index = spl_object_id($taskList);

        $this->taskLists[$index] = [
            'current' => $taskList,
            'previous' => array_map(static function (Task $task): array {
                return [
                    'completed' => $task->isCompleted(),
                    'running' => $task->isRunning(),
                ];
            }, iterator_to_array($taskList->getIterator())),
        ];
    }

    private function prepareProcessOutput(string $output): string
    {
        return preg_replace('/[\n\r]/', PHP_EOL, $output) ?? '';
    }
}
