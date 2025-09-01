<?php
namespace Craft\Drive\View;

use Craft\Interface\ViewEngine;

/**
 * TwigDrive class for rendering Twig templates.
 * This class extends the View class to provide functionality for rendering Twig templates.
 */
class TwigDrive implements ViewEngine
{
    protected $viewPath;
    protected $options;

    public function __construct($viewPath, $options = [])
    {
        $this->viewPath = $viewPath;
        $this->options = $options;
    }

    public function render(string $template, array $data = []): string
    {
        // TODO: Triển khai thực tế với package twig
        throw new \Exception('TwigDrive: Please install twig package and implement render()');
    }
}