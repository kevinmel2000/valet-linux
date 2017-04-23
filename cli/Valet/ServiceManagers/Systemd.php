<?php

namespace Valet\ServiceManagers;

use DomainException;
use Valet\CommandLine;
use Valet\Contracts\ServiceManager;

class Systemd implements ServiceManager
{
    var $cli;

    /**
     * Create a new Brew instance.
     *
     * @param  CommandLine $cli
     */
    public function __construct(CommandLine $cli)
    {
        $this->cli = $cli;
    }

    /**
     * Start the given services.
     *
     * @param
     * @return void
     */
    function start($services)
    {
        $services = is_array($services) ? $services : func_get_args();

        foreach ($services as $service) {
            $this->enable($this->getRealService($service));
            info("Starting $service...");
            $this->cli->quietly('sudo systemctl start ' . $this->getRealService($service));
        }
    }

    /**
     * Stop the given services.
     *
     * @param
     * @return void
     */
    function stop($services)
    {
        $services = is_array($services) ? $services : func_get_args();

        foreach ($services as $service) {
            info("Stopping $service...");
            $this->cli->quietly('sudo systemctl stop ' . $this->getRealService($service));
        }
    }

    /**
     * Restart the given services.
     *
     * @param
     * @return void
     */
    function restart($services)
    {
        $services = is_array($services) ? $services : func_get_args();

        foreach ($services as $service) {
            $this->enable($this->getRealService($service));
            info("Restarting $service...");
            $this->cli->quietly('sudo systemctl restart ' . $this->getRealService($service));
        }
    }

    /**
     * Status of the given services.
     *
     * @param
     * @return void
     */
    public function printStatus($services)
    {
        $services = is_array($services) ? $services : func_get_args();

        foreach ($services as $service) {
            $status = $this->cli->run('systemctl status '.$this->getRealService($service).' | grep "Active:"');
            $running = strpos(trim($status), 'running');

            if ($running) {
                info(ucfirst($service).' is running...');
            } else {
                warning(ucfirst($service).' is stopped...');
            }
        }
    }

    /**
     * Status of the given services.
     *
     * @param
     * @return void
     */
    public function status($service)
    {
        return $this->cli->run('systemctl status '.$this->getRealService($service));
    }

    /**
     * Determine if service manager is available on the system.
     *
     * @return bool
     */
    function isAvailable()
    {
        try {
            $output = $this->cli->run('which systemctl', function ($exitCode, $output) {
                throw new DomainException('Systemd not available');
            });

            return $output != '';
        } catch (DomainException $e) {
            return false;
        }
    }

    /**
     * Determine if service is enabled on the system and enable if not.
     *
     * @return void
     */
    function enable($service)
    {
        $enabled = strpos($this->cli->run('systemctl list-unit-files | grep enabled | grep ' . $service), $service);

        if (! $enabled) {
            $this->cli->run('systemctl enable ' . $service);
            info("Enabled {$service}...");
        }
    }

    /**
     * Determine real service name
     *
     * @param string $service
     * @return string
     */
    function getRealService($service)
    {
        return collect($service)->first(function ($service) {
            return !strpos($this->cli->run('systemctl status ' . $service), 'could not be found');
        }, function () {
            throw new DomainException("Unable to determine service name.");
        });
    }
}
