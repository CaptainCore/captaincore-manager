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
        $this->email    = $email;
        $this->password = $password;
    }

    protected function login($email, $password, $oneTimePassword = null)
    {
        $this->browser->request('GET', static::LOGIN_URL);

        $this->browser->submitForm('Log in', [
            'email'    => $email,
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

    public function newSite($domain)
    {
        $sites = self::getSites();
        foreach( $sites as $site ) {
            if ( $site->name == $domain ) {
                return (object) [ 
                    "tracking_id"    => $site->tracking_id,
                    "name"           => $domain,
                    "response"       => "Tracking already exists for $domain",
                ];
            }
        }

        $login = wp_remote_get( "https://app.usefathom.com/login" );
        if ( is_wp_error( $login ) ) {
            echo "Error: Couldn't retrieve Fathom login page\n";
            return;
        }
        preg_match( '/_token\" value=\"(.+)\"/', $login['body'],  $token );

        $auth = wp_remote_post( "https://app.usefathom.com/login", [ 
            'method'  => 'POST', 
            'cookies' => $login['cookies'],
            'headers' => [ 'Content-Type' => 'application/json; charset=utf-8' ],
            'body'    => json_encode( [ 
                "_token" => $token[1],
                "email"    => $this->email,
                "password" => $this->password,
                "remember" => "on" ] )
        ] );
        if ( is_wp_error( $auth ) ) {
            echo "Error: Couldn't login to Fathom\n";
            return;
        }
        $home = wp_remote_get( "https://app.usefathom.com", [ 'method'  => 'GET', 'cookies' => $auth['cookies'] ] );
        if ( is_wp_error( $home ) ) {
            echo "Error: Couldn't fetch Fathom's home page\n";
            return;
        }
        preg_match( '/window\.xsrf_token = \"(.*)\"/', $home['body'], $token );

        $response = wp_remote_post( "https://app.usefathom.com/sites", [ 
            'method'  => 'POST',
            'cookies' => $home['cookies'],
            'headers' => [ 'Content-Type' => 'application/json; charset=utf-8', "x-csrf-token" => $token[1] ],
            'body' => json_encode( [ 
                "site" => [ 
                    "tracking_id"    => null,
                    "name"           => $domain,
                    "sharing"        => "none",
                    "share_url"      => null, 
                    "share_password" => null,
                    "monitor"        => null
                ]
            ] )
        ] );
        if ( is_wp_error( $response ) ) {
            echo "Error: Couldn't fetch Fathom's response\n";
            return;
        }
        return json_decode( $response['body'] )->Site;
    }
}
