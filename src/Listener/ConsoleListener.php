<?php

declare(strict_types=1);

namespace Nusje2000\ProcessRunner\Listener;

use Nusje2000\ProcessRunner\Task;
use Nusje2000\ProcessRunner\TaskList;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;

final class ConsoleListener implements ExecutionListener
{
    /**
     * @var ConsoleOutputInterface
     */
    protected $output;

    /**
     * @var int
     */
    protected $priority;

    /**
     * @var array<int, ConsoleSectionOutput>
     */
    protected $outputSections = [];

    /**
     * @var array<int, TaskList>
     */
    protected $taskLists = [];

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
        $section = $this->getOutputSection($taskList);

        $buffer = [];
        foreach ($taskList->getIterator() as $task) {
            $buffer[] = sprintf('%s (%s)', $task->getName(), $this->getStatusText($task));
        }

        $buffer[] = '';

        foreach ($taskList->getFailedTasks()->getIterator() as $task) {
            $buffer[] = sprintf('<error>Task "%s" failed (exit code: %d).</error>', $task->getName(), $task->getProcess()->getExitCode());

            $output = $task->getProcess()->getOutput();
            if ('' !== $output) {
                $buffer[] = 'Output:';
                $buffer[] = $this->prepareProcessOutput($output);
            }

            $output = $task->getProcess()->getErrorOutput();
            if ('' !== $output) {
                $buffer[] = 'Error output:';
                $buffer[] = $this->prepareProcessOutput($output);
            }

            $buffer[] = '';
        }

        $section->clear();
        $section->writeln($buffer);
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    private function getOutputSection(TaskList $taskList): ConsoleSectionOutput
    {
        $hash = spl_object_id($taskList);

        if (!in_array($taskList, $this->taskLists, true)) {
            $this->taskLists[] = $taskList;
        }

        if (isset($this->outputSections[$hash])) {
            return $this->outputSections[$hash];
        }

        $section = $this->output->section();
        $this->outputSections[$hash] = $section;

        return $section;
    }

    private function getStatusText(Task $task): string
    {
        if ($task->isIdle()) {
            return '<idle>idle</idle>';
        }

        if ($task->isRunning()) {
            return '<running>running</running>';
        }

        if ($task->isSuccessfull()) {
            return '<success>success</success>';
        }

        if ($task->isFailed()) {
            return sprintf('<error>failed (exit code: %d)</error>', $task->getProcess()->getExitCode());
        }

        return 'unknown process status';
    }

    private function prepareProcessOutput(string $output): string
    {
        return preg_replace('/[\n\r]/', PHP_EOL, $output) ?? '';
    }
}
