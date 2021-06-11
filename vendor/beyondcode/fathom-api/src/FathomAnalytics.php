<?php

namespace BeyondCode\FathomAnalytics;

use Carbon\Carbon;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;

class FathomAnalytics
{
    const LOGIN_URL = 'https://app.usefathom.com/login';
    const TWO_FACTOR_AUTH_URL = 'https://app.usefathom.com/2fa';

    /**
     * @var HttpBrowser
     */
    protected $browser;

    /**
     * FathomAnalytics constructor.
     * @param $email
     * @param $password
     */
    public function __construct($email, $password)
    {
        $this->browser = new HttpBrowser(HttpClient::create());

        $this->login($email, $password);
    }

    protected function login($email, $password, $oneTimePassword = null)
    {
        $this->browser->request('GET', static::LOGIN_URL);

        $this->browser->submitForm('Log in', [
            'email' => $email,
            'password' => $password
        ]);

        if($this->browser->getHistory()->current()->getUri() === static::TWO_FACTOR_AUTH_URL) {
            $this->browser->submitForm('Log in', [
                'one_time_password' => $oneTimePassword,
            ]);
        }
    }

    public function getSites()
    {
        $result = '';
        $this->browser->getCrawler()->filter('script')->each(function (Crawler $element) use (&$result) {
            $scriptContent = $element->text();
            if (strpos($scriptContent, 'window.data') === 0) {
                preg_match('/window\.data = (.*?);/m', $scriptContent, $matches);
                $result = json_decode($matches[1]);
            }
        });

        return $result->sites;
    }

    public function getCurrentVisitors($siteId)
    {
        $this->browser->request('GET', "https://app.usefathom.com/sites/{$siteId}/current_visitors");

        return json_decode($this->browser->getResponse()->getContent());
    }

    public function getData($siteId, Carbon $from = null, Carbon $to = null)
    {
        $from = $from ?? Carbon::today()->startOfDay();
        $to = $to ?? Carbon::today()->endOfDay();

        $this->browser->request('GET', "https://app.usefathom.com/sites/{$siteId}/data?from={$from->toDateTimeString()}&to={$to->toDateTimeString()}&site={$siteId}&range=today&tz=Europe%2FBerlin");
        return json_decode($this->browser->getResponse()->getContent());
    }
}
