<?php

/**
 * Class Template
 */
class Template
{
    /**
     * @var CI_Controller
     */
    private $ci = NULL;    //codeigniter instance
    /**
     * @var array
     */
    private $config = []; //the theme config
    /**
     * @var string
     */
    private $content = '';      //the content (filled by the view/theme function)
    /**
     * @var array
     */
    private $data = []; //the data (variables passed to the theme and views)
    /**
     * @var array
     */
    private $messages = []; //messages to display

    /**
     * @var null
     */
    private $module = NULL;          //current module
    /**
     * @var null
     */
    private $controller = NULL;      //current controller
    /**
     * @var null
     */
    private $method = NULL;          //current method

    /**
     * @var array
     */
    private $template_locations = [];

    /**
     * Template constructor.
     */
    public function __construct()
    {
        //get the CI instance
        $this->ci = &get_instance();
        //get the config
        $this->config = config_item('theme');
        //set the theme
        $this->setTheme($this->config['theme']);
        if (method_exists($this->ci->router, 'fetch_module')) {
            $this->module = $this->ci->router->fetch_module();
        }
        // What controllers or methods are in use
        $this->controller = $this->ci->router->class;
        $this->method = $this->ci->router->method;
        $this->template_locations = [
            $this->config('path') . $this->config('theme') . '/views/modules/' . $this->module . '/',
            $this->config('path') . $this->config('theme') . '/views/',
            $this->config('path') . 'default/views/modules/' . $this->module . '/',
            $this->config('path') . 'default/views/',
            APPPATH . 'modules/' . $this->module . '/views/'
        ];
    }

    /**
     * Template::setTheme()
     *
     * Sets the theme
     *
     * @param string $theme The theme
     * @return self
     */
    public function setTheme(string $theme = 'default'): self
    {
        $this->setConfig('theme', $theme);
        $functions = $this->config('path') . $this->config('theme') . '/functions.php';
        if (file_exists($functions)) {
            include($functions);
        }
        $this->template_locations = [
            $this->config('path') . $this->config('theme') . '/views/modules/' . $this->module . '/',
            $this->config('path') . $this->config('theme') . '/views/',
            $this->config('path') . 'default/views/modules/' . $this->module . '/',
            $this->config('path') . 'default/views/',
            APPPATH . 'modules/' . $this->module . '/views/'
        ];
        return $this;
    }

    /**
     * Template::setLayout()
     *
     * Sets the layout for the current theme (default: index => index.php)
     *
     * @param string $layout The layout for the theme
     * @return Template
     */
    public function setLayout(string $layout = 'index'): self
    {
        $path = $this->config('path') . $this->config('theme') . '/' . $layout . '.php';
        if (!file_exists($path)) {
            $layout = 'index';
        }
        $this->setConfig('layout', $layout);
        return $this;
    }

    /**
     * Template::addMessage()
     *
     * Adds a message to the queue
     *
     * @param string $message The message to display
     * @param string $type Can be anything: info,success,error,warning
     * @return Template
     */
    public function addMessage(string $message, string $type = 'info'): self
    {
        $this->messages[] = [
            'message' => $message,
            'type'    => $type,
        ];
        return $this;
    }

    /**
     * Template::setMessages()
     *
     * Sets all messages (handy for flash ops)
     *
     * @param array $messages Messages to be set
     * @return Template
     */
    public function setMessages(array $messages = []): self
    {
        if (!empty($messages)) {
            $this->messages = $messages;
        }
        return $this;
    }

    /**
     * Template::clearMessages()
     *
     * Removes all messages
     *
     * @return Template
     */
    public function clearMessages(): self
    {
        $this->messages = [];
        return $this;
    }

    /**
     * Template::setConfig()
     *
     * Sets an item in the config array
     * e.g. $this->theme->set_config('theme', 'other_theme');
     *
     * @param mixed $name
     * @param mixed $value
     * @return Template
     */
    public function setConfig($name, $value): self
    {
        $this->config[$name] = $value;
        return $this;
    }

    /**
     * Template::get()
     *
     * Gets an item from the data array
     * e.g. $this->theme->get('current_user');
     *
     * @param string $name The value to get
     * @param bool $default (optional: FALSE)
     * @return mixed or $default if not found
     */
    public function get(string $name, bool $default = FALSE)
    {
        return isset($this->data[$name]) ? $this->data[$name] : $default;
    }

    /**
     * Template::set()
     *
     * Sets an item in the data array
     * e.g. $this->theme->set('current_user', $this->user);
     *
     * @param string $name The item to set
     * @param mixed $value The value to set
     * @return Template
     */
    public function set(string $name, $value): self
    {
        $this->data[$name] = $value;
        return $this;
    }

    /**
     * Template::messages()
     *
     * Returns an unordered list (HTML) for the message or
     * the message array. depending on the $html variable
     *
     * @param bool $html Return it as html? (false=array)
     * @return string(html) or array
     */
    public function messages($html = TRUE)
    {
        if (!$html) {
            return $this->messages;
        }

        $html = '';
        $html .= '<ul class="messages">';
        foreach ($this->messages as $message) {
            $html .= sprintf('<li class="%s">%s</li>', $message['type'], $message['message']);
        }
        $html .= '</ul>';
        return $html;
    }

    /**
     * Template::content()
     *
     * Returns the content variable (filled by the view/theme function)
     *
     * @return string
     */
    public function content()
    {
        return $this->content;
    }

    /**
     * @param $type
     * @param $data
     * @return mixed|null
     * @throws Exception
     */
    public function meta($type, $data)
    {
        if (!in_array($type, ['css', 'js'])) {
            show_error('the type of meta is noy supported');
        }
        $result = null;
        if (is_array($data)) {
            foreach ($data as $css) {
                $result .= $this->checkStaticFile($type, $css);
            }
            return $result;
        }
        if (filter_var($data, FILTER_VALIDATE_URL)) {
            show_error('I dont understand urls.');
        }
        return $this->checkStaticFile($type, $data);
    }

    /**
     * Template::view()
     *
     * Loads the view just as CI would normally do and
     * passed it to the theme function wrapping the view into the theme
     *
     * @param string $view The view to load
     * @param array $data The data array to pass to the view
     * @param bool $return (optional) Return the output?
     * @return mixed
     */
    public function view(string $view, array $data = [], bool $return = false)
    {
        $data = array_merge($this->data, $data);
        $content = $this->partial($view, $data, true);
        return $this->render($content, $return);
    }

    /**
     * Template::config()
     *
     * Returns an item from the config array
     *
     * @param string $name
     * @param bool $default (optional: FALSE)
     * @return mixed or $default if not found
     */
    private function config(string $name, bool $default = FALSE)
    {
        return isset($this->config[$name]) ? $this->config[$name] : $default;
    }

    /**
     * Template::render()
     *
     * Wraps the theme around the $content
     *
     * @param string $content Raw HTML content
     * @param bool $return (optional) Return the output?
     * @return mixed
     */
    private function render(string $content, bool $return = false)
    {
        $this->content = $content;

        extract($this->data);

        $theme = $this->config('path') . $this->config('theme') . '/' . $this->config('layout') . '.php';
        if (!file_exists($theme)) {
            if ($this->config('theme') != "default") {
                //save the original requested themes for the error message, if the default theme also not exist
                $theme_requested = $theme;
                $theme = $this->config('path') . 'default/index.php';

                if (!file_exists($theme)) {
                    show_error('Make sure you configurate your theme <small>(did you copy the <u>themes</u> folder to your root?)</small><br><br>Requested Theme: ' . $theme_requested . ' not found.<br />Default Theme: ' . $theme . ' not found.');
                } else {
                    $this->setTheme();
                }
            } else {
                show_error('Make sure you configurate your theme <small>(did you copy the <u>themes</u> folder to your root?)</small><br><br>Default Theme: ' . $theme . ' not found.');
            }
        }

        ob_start();
        include($theme);
        $html = ob_get_contents();
        ob_end_clean();
        $html = preg_replace_callback('~((href|src)\s*=\s*[\"\'])([^\"\']+)~i', [$this, '_replace_url'], $html);
        $html = str_replace('{template_url}', $this->config('url') . $this->config('theme'), $html);
        if ($return) {
            return $html;
        }
        return get_instance()->output->set_output($html);
    }

    /**
     * Template::partial()
     *
     * Loads the view just as CI except this function will look
     * first into the theme's subdir 'views' to find the view
     *
     * @param string $view The view to load
     * @param array $data The data array to pass to the view
     * @param bool $return (optional) Return the output?
     * @return mixed
     */
    private function partial(string $view, array $data = [], bool $return = false)
    {
        $data   = is_array($data) ? $data : [];
        $data   = array_merge($this->data, $data);
        $path   = NULL;
        $output = null;

        foreach ($this->template_locations as $location) {
            if (file_exists($location . $view . '.php') && $path == NULL) {
                $path = $location . $view . '.php';
                extract($data);
                ob_start();
                include($path);
                $output = ob_get_contents();
                ob_end_clean();
            }
        }

        if ($path == NULL) {
            $output = get_instance()->load->view($view, $data, TRUE);
        }

        if ($return) {
            return $output;
        }
        return $output;
    }

    /**
     * Template::_replace_url()
     *
     * @param mixed $x
     * @return string
     */
    private static function _replace_url($x)
    {
        $url = isset($x[3]) ? $x[3] : '';
        if (strpos($url, 'http') !== 0 &&
            strpos($url, 'mailto') !== 0 &&
            strpos($url, '/') !== 0 &&
            strpos($url, '#') !== 0 &&
            strpos($url, 'javascript') !== 0 &&
            strpos($url, '{') !== 0
        ) {
            $url = '{template_url}/' . $url;
        }
        return isset($x[1]) ? ($x[1] . $url) : $url;
    }

    /**
     * @param $type
     * @param $file
     * @return mixed
     * @throws Exception
     */
    private function checkStaticFile($type, $file)
    {
        $result = null;
        $path = $this->config('path') . $this->config('theme') . '/' . $type . '/' . $file . '.' . $type;
        if (file_exists($path)) {
            throw new Exception('File is not exists: ' . $path);
        }
        $url = $this->config('url') . $this->config('theme') . '/' . $type . '/' . $file;
        switch ($type) {
            case 'css':
                $result = '<link href="' . $url . '" rel="stylesheet">' . PHP_EOL;
                break;
            case 'js':
                $result = '<script src="' . $url . '"></script>' . PHP_EOL;
                break;
        }
        return $result;
    }
}