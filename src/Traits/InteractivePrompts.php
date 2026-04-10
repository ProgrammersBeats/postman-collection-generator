<?php

declare(strict_types=1);

namespace YourVendor\PostmanGenerator\Traits;

use Illuminate\Support\Str;
use YourVendor\PostmanGenerator\DTOs\GeneratorOptions;

/**
 * Trait for handling interactive prompts with fallback for older Laravel versions.
 */
trait InteractivePrompts
{
    /**
     * Check if Laravel Prompts package is available.
     */
    protected function hasLaravelPrompts(): bool
    {
        return function_exists('Laravel\Prompts\select');
    }

    /**
     * Display a select prompt with fallback.
     */
    protected function selectPrompt(string $label, array $options, string $default = ''): string
    {
        if ($this->hasLaravelPrompts()) {
            return \Laravel\Prompts\select(
                label: $label,
                options: $options,
                default: $default,
            );
        }

        // Fallback to standard choice
        $keys = array_keys($options);
        $labels = array_values($options);

        $defaultIndex = array_search($default, $keys);
        if ($defaultIndex === false) {
            $defaultIndex = 0;
        }

        $choice = $this->choice($label, $labels, $defaultIndex);

        // Find the key by the label
        $index = array_search($choice, $labels);
        return $keys[$index] ?? $default;
    }

    /**
     * Display a text prompt with fallback.
     */
    protected function textPrompt(string $label, string $default = '', string $hint = ''): string
    {
        if ($this->hasLaravelPrompts()) {
            return \Laravel\Prompts\text(
                label: $label,
                default: $default,
                hint: $hint,
            );
        }

        $question = $label;
        if ($hint) {
            $question .= " ({$hint})";
        }

        return $this->ask($question, $default) ?? $default;
    }

    /**
     * Display a confirm prompt with fallback.
     */
    protected function confirmPrompt(string $label, bool $default = true, string $hint = ''): bool
    {
        if ($this->hasLaravelPrompts()) {
            return \Laravel\Prompts\confirm(
                label: $label,
                default: $default,
                hint: $hint,
            );
        }

        return $this->confirm($label, $default);
    }

    /**
     * Display a multiselect prompt with fallback.
     */
    protected function multiselectPrompt(string $label, array $options, array $default = [], string $hint = ''): array
    {
        if ($this->hasLaravelPrompts()) {
            return \Laravel\Prompts\multiselect(
                label: $label,
                options: $options,
                default: $default,
                hint: $hint,
            );
        }

        // Fallback: Show checkboxes one by one
        $selected = [];
        $this->info($label);
        if ($hint) {
            $this->line("<fg=gray>{$hint}</>");
        }

        foreach ($options as $key => $label) {
            $isDefault = in_array($key, $default);
            if ($this->confirm("  Include {$label}?", $isDefault)) {
                $selected[] = $key;
            }
        }

        return $selected;
    }

    /**
     * Display a spinner with fallback.
     */
    protected function spinnerPrompt(callable $callback, string $message): mixed
    {
        if ($this->hasLaravelPrompts()) {
            return \Laravel\Prompts\spin($callback, $message);
        }

        // Fallback: Just show message and execute
        $this->info($message);
        return $callback();
    }

    /**
     * Display info message with fallback.
     */
    protected function infoPrompt(string $message): void
    {
        if ($this->hasLaravelPrompts()) {
            \Laravel\Prompts\info($message);
            return;
        }

        $this->info($message);
    }

    /**
     * Display outro message with fallback.
     */
    protected function outroPrompt(string $message): void
    {
        if ($this->hasLaravelPrompts()) {
            \Laravel\Prompts\outro($message);
            return;
        }

        $this->newLine();
        $this->info($message);
    }

    /**
     * Display warning message with fallback.
     */
    protected function warningPrompt(string $message): void
    {
        if ($this->hasLaravelPrompts()) {
            \Laravel\Prompts\warning($message);
            return;
        }

        $this->warn($message);
    }
}
