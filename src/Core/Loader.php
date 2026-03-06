<?php
/**
 * Hook Loader
 *
 * Manages all WordPress hooks (actions and filters) for the plugin.
 *
 * @package    InvoiceForge
 * @subpackage Core
 * @since      1.0.0
 */

declare(strict_types=1);

namespace InvoiceForge\Core;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hook loader class.
 *
 * This class stores all actions and filters and registers them
 * with WordPress when the run() method is called.
 *
 * @since 1.0.0
 */
class Loader
{
    /**
     * The array of actions registered with WordPress.
     *
     * @since 1.0.0
     * @var array<int, array{hook: string, component: object|null, callback: string|callable, priority: int, accepted_args: int}>
     */
    private array $actions = [];

    /**
     * The array of filters registered with WordPress.
     *
     * @since 1.0.0
     * @var array<int, array{hook: string, component: object|null, callback: string|callable, priority: int, accepted_args: int}>
     */
    private array $filters = [];

    /**
     * Add a new action to the collection to be registered with WordPress.
     *
     * @since 1.0.0
     *
     * @param string          $hook          The name of the WordPress action.
     * @param object|null     $component     The object instance (or null for functions).
     * @param string|callable $callback      The callback method name or callable.
     * @param int             $priority      The priority at which the callback should be fired.
     * @param int             $accepted_args The number of arguments that should be passed to the callback.
     * @return self Returns self for method chaining.
     */
    public function addAction(
        string $hook,
        ?object $component,
        string|callable $callback,
        int $priority = 10,
        int $accepted_args = 1
    ): self {
        $this->actions = $this->add(
            $this->actions,
            $hook,
            $component,
            $callback,
            $priority,
            $accepted_args
        );

        return $this;
    }

    /**
     * Add a new filter to the collection to be registered with WordPress.
     *
     * @since 1.0.0
     *
     * @param string          $hook          The name of the WordPress filter.
     * @param object|null     $component     The object instance (or null for functions).
     * @param string|callable $callback      The callback method name or callable.
     * @param int             $priority      The priority at which the callback should be fired.
     * @param int             $accepted_args The number of arguments that should be passed to the callback.
     * @return self Returns self for method chaining.
     */
    public function addFilter(
        string $hook,
        ?object $component,
        string|callable $callback,
        int $priority = 10,
        int $accepted_args = 1
    ): self {
        $this->filters = $this->add(
            $this->filters,
            $hook,
            $component,
            $callback,
            $priority,
            $accepted_args
        );

        return $this;
    }

    /**
     * Add a hook to the collection.
     *
     * @since 1.0.0
     *
     * @param array<int, array{hook: string, component: object|null, callback: string|callable, priority: int, accepted_args: int}> $hooks         The collection of hooks.
     * @param string          $hook          The hook name.
     * @param object|null     $component     The object instance.
     * @param string|callable $callback      The callback.
     * @param int             $priority      The priority.
     * @param int             $accepted_args The number of accepted arguments.
     * @return array<int, array{hook: string, component: object|null, callback: string|callable, priority: int, accepted_args: int}> The updated hooks collection.
     */
    private function add(
        array $hooks,
        string $hook,
        ?object $component,
        string|callable $callback,
        int $priority,
        int $accepted_args
    ): array {
        $hooks[] = [
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        ];

        return $hooks;
    }

    /**
     * Register all hooks with WordPress.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function run(): void
    {
        // Register filters
        foreach ($this->filters as $hook) {
            $callback = $this->getCallback($hook['component'], $hook['callback']);
            add_filter(
                $hook['hook'],
                $callback,
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        // Register actions
        foreach ($this->actions as $hook) {
            $callback = $this->getCallback($hook['component'], $hook['callback']);
            add_action(
                $hook['hook'],
                $callback,
                $hook['priority'],
                $hook['accepted_args']
            );
        }
    }

    /**
     * Get the callback for a hook.
     *
     * @since 1.0.0
     *
     * @param object|null     $component The component object.
     * @param string|callable $callback  The callback method or callable.
     * @return callable The callback.
     */
    private function getCallback(?object $component, string|callable $callback): callable
    {
        if ($component !== null && is_string($callback)) {
            return [$component, $callback];
        }

        return $callback;
    }

    /**
     * Get all registered actions.
     *
     * @since 1.0.0
     *
     * @return array<int, array{hook: string, component: object|null, callback: string|callable, priority: int, accepted_args: int}> The actions array.
     */
    public function getActions(): array
    {
        return $this->actions;
    }

    /**
     * Get all registered filters.
     *
     * @since 1.0.0
     *
     * @return array<int, array{hook: string, component: object|null, callback: string|callable, priority: int, accepted_args: int}> The filters array.
     */
    public function getFilters(): array
    {
        return $this->filters;
    }
}
