<?php
namespace App\Controller;

class Controller
{
    protected $viewEngine = null;

    /**
     * Dispatch method to call the specified action with parameters.
     * @param string $action 
     * @param array $params
     * @return mixed
     */
    public function dispatch($action, $params = [])
    {
        if (method_exists($this, $action)) {
            return call_user_func_array([$this, $action], $params);
        } else {
            \Craft\Application\View::abort(404,\Craft\Application\View::resource('error/404.php'));
        }
    }

    /**
     * Render view with data.
     * @param string $view View name to render (directory at: resource/view/)
     * @param array $data Data to pass to the view
     */
    public function render($view, $data = []): void
    {
        $viewObj = new \Craft\Application\View(null);
        echo $viewObj->view($view, $data);
    }
}
