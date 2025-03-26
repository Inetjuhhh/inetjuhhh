<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Jorenvh\Share\Share;
use Jorenvh\Share\ShareFacade;

class SocialShare extends Component
{
    public $url;
    public $text;

    public function __construct($url, $text)
    {
        $this->url = $url;
        $this->text = $text;
    }

    public function render()
    {
        $shareFB = ShareFacade::page($this->url, $this->text)->facebook();
        $shareLI = ShareFacade::page($this->url, $this->text)->linkedin();
        $shareRD = ShareFacade::page($this->url, $this->text)->reddit();

        return view('components.social-share', [
            'shareFB' => $shareFB,
            'shareLI' => $shareLI,
            'shareRD' => $shareRD,
        ]);
    }
}
