<?php
/**
 * LUB-TEK - Lightweight Event Dispatcher
 * Allows decoupled communication between controllers and automated notifications/actions.
 */

class EventDispatcher
{
    private static $listeners = [];

    /**
     * Register an event listener.
     *
     * @param string $eventName
     * @param callable $listener
     */
    public static function addListener(string $eventName, callable $listener)
    {
        if (!isset(self::$listeners[$eventName])) {
            self::$listeners[$eventName] = [];
        }

        // Evita registrar o mesmo listener mais de uma vez para o mesmo evento
        // (previne crescimento indefinido / disparos duplicados em cenários onde
        // o bootstrap que registra listeners roda mais de uma vez no mesmo processo,
        // como em SAPIs de processo persistente).
        foreach (self::$listeners[$eventName] as $existing) {
            if ($existing === $listener) {
                return;
            }
        }

        self::$listeners[$eventName][] = $listener;
    }

    /**
     * Remove a previously registered listener from an event.
     *
     * @param string $eventName
     * @param callable $listener
     * @return bool true if a listener was found and removed
     */
    public static function removeListener(string $eventName, callable $listener): bool
    {
        if (!isset(self::$listeners[$eventName]) || !is_array(self::$listeners[$eventName])) {
            return false;
        }

        foreach (self::$listeners[$eventName] as $idx => $existing) {
            if ($existing === $listener) {
                unset(self::$listeners[$eventName][$idx]);
                self::$listeners[$eventName] = array_values(self::$listeners[$eventName]);
                return true;
            }
        }

        return false;
    }

    /**
     * Remove all listeners for a given event, or all listeners for all events
     * when no event name is given. Useful to prevent listener leaks in
     * long-running/persistent process contexts (workers, tests, etc).
     *
     * @param ?string $eventName
     */
    public static function clearListeners(?string $eventName = null): void
    {
        if ($eventName === null) {
            self::$listeners = [];
            return;
        }

        unset(self::$listeners[$eventName]);
    }

    /**
     * Dispatch an event, notifying all registered listeners.
     *
     * @param string $eventName
     * @param mixed $payload
     */
    public static function dispatch(string $eventName, $payload = [])
    {
        if (isset(self::$listeners[$eventName]) && is_array(self::$listeners[$eventName])) {
            foreach (self::$listeners[$eventName] as $listener) {
                try {
                    $listener($payload);
                } catch (Throwable $e) {
                    error_log("Event listener error on event '$eventName': " . $e->getMessage() . 
                              " in " . $e->getFile() . ":" . $e->getLine());
                }
            }
        }
    }
}
