<?php
namespace Craft\Application\Attribute;

#[\Attribute]
class View
{
    public string $view;
    public array $data;

    /**
     * View attribute constructor.
     *
     * This constructor initializes the view attribute with the provided values.
     *
     * @param string $view The view name
     * @param array $data The data to be passed to the view
     */
    public function __construct(string $view, array $data = [])
    {
        $this->view = $view;
        $this->data = $data;
    }
}