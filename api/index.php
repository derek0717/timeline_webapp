<?php
//error_reporting(E_ALL | E_STRICT);
//ini_set('display_errors', 'On');
error_reporting(0);
ini_set('display_errors', 0);

$twitter_settings = array(
    'oauth_access_token' => "",
    'oauth_access_token_secret' => "",
    'consumer_key' => "",
    'consumer_secret' => ""
);

/**
 * Twitter-API-PHP : Simple PHP wrapper for the v1.1 API
 *
 * PHP version 5.3.10
 *
 * @category Awesomeness
 * @package  Twitter-API-PHP
 * @author   James Mallison <me@j7mbo.co.uk>
 * @license  MIT License
 * @version  1.0.4
 * @link     http://github.com/j7mbo/twitter-api-php
 */
class TwitterAPIExchange
{
    /**
     * @var string
     */
    private $oauth_access_token;
    /**
     * @var string
     */
    private $oauth_access_token_secret;
    /**
     * @var string
     */
    private $consumer_key;
    /**
     * @var string
     */
    private $consumer_secret;
    /**
     * @var array
     */
    private $postfields;
    /**
     * @var string
     */
    private $getfield;
    /**
     * @var mixed
     */
    protected $oauth;
    /**
     * @var string
     */
    public $url;
    /**
     * @var string
     */
    public $requestMethod;
    /**
     * The HTTP status code from the previous request
     *
     * @var int
     */
    protected $httpStatusCode;

    /**
     * Create the API access object. Requires an array of settings::
     * oauth access token, oauth access token secret, consumer key, consumer secret
     * These are all available by creating your own application on dev.twitter.com
     * Requires the cURL library
     *
     * @throws \RuntimeException When cURL isn't loaded
     * @throws \InvalidArgumentException When incomplete settings parameters are provided
     *
     * @param array $settings
     */
    public function __construct(array $settings)
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('TwitterAPIExchange requires cURL extension to be loaded, see: http://curl.haxx.se/docs/install.html');
        }
        if (!isset($settings['oauth_access_token'])
            || !isset($settings['oauth_access_token_secret'])
            || !isset($settings['consumer_key'])
            || !isset($settings['consumer_secret'])) {
            throw new InvalidArgumentException('Incomplete settings passed to TwitterAPIExchange');
        }
        $this->oauth_access_token = $settings['oauth_access_token'];
        $this->oauth_access_token_secret = $settings['oauth_access_token_secret'];
        $this->consumer_key = $settings['consumer_key'];
        $this->consumer_secret = $settings['consumer_secret'];
    }

    /**
     * Set postfields array, example: array('screen_name' => 'J7mbo')
     *
     * @param array $array Array of parameters to send to API
     *
     * @return TwitterAPIExchange Instance of self for method chaining
     * @throws \Exception When you are trying to set both get and post fields
     *
     */
    public function setPostfields(array $array)
    {
        if (!is_null($this->getGetfield())) {
            throw new Exception('You can only choose get OR post fields (post fields include put).');
        }
        if (isset($array['status']) && substr($array['status'], 0, 1) === '@') {
            $array['status'] = sprintf("\0%s", $array['status']);
        }
        foreach ($array as $key => &$value) {
            if (is_bool($value)) {
                $value = ($value === true) ? 'true' : 'false';
            }
        }
        $this->postfields = $array;
        // rebuild oAuth
        if (isset($this->oauth['oauth_signature'])) {
            $this->buildOauth($this->url, $this->requestMethod);
        }
        return $this;
    }

    /**
     * Set getfield string, example: '?screen_name=J7mbo'
     *
     * @param string $string Get key and value pairs as string
     *
     * @return \TwitterAPIExchange Instance of self for method chaining
     * @throws \Exception
     *
     */
    public function setGetfield($string)
    {
        if (!is_null($this->getPostfields())) {
            throw new Exception('You can only choose get OR post / post fields.');
        }
        $getfields = preg_replace('/^\?/', '', explode('&', $string));
        $params = array();
        foreach ($getfields as $field) {
            if ($field !== '') {
                list($key, $value) = explode('=', $field);
                $params[$key] = $value;
            }
        }
        $this->getfield = '?' . http_build_query($params, '', '&');
        return $this;
    }

    /**
     * Get getfield string (simple getter)
     *
     * @return string $this->getfields
     */
    public function getGetfield()
    {
        return $this->getfield;
    }

    /**
     * Get postfields array (simple getter)
     *
     * @return array $this->postfields
     */
    public function getPostfields()
    {
        return $this->postfields;
    }

    /**
     * Build the Oauth object using params set in construct and additionals
     * passed to this method. For v1.1, see: https://dev.twitter.com/docs/api/1.1
     *
     * @param string $url The API url to use. Example: https://api.twitter.com/1.1/search/tweets.json
     * @param string $requestMethod Either POST or GET
     *
     * @throws \Exception
     *
     * @return \TwitterAPIExchange Instance of self for method chaining
     */
    public function buildOauth($url, $requestMethod)
    {
        if (!in_array(strtolower($requestMethod), array('post', 'get', 'put', 'delete'))) {
            throw new Exception('Request method must be either POST, GET or PUT or DELETE');
        }
        $consumer_key = $this->consumer_key;
        $consumer_secret = $this->consumer_secret;
        $oauth_access_token = $this->oauth_access_token;
        $oauth_access_token_secret = $this->oauth_access_token_secret;
        $oauth = array(
            'oauth_consumer_key' => $consumer_key,
            'oauth_nonce' => time(),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_token' => $oauth_access_token,
            'oauth_timestamp' => time(),
            'oauth_version' => '1.0'
        );
        $getfield = $this->getGetfield();
        if (!is_null($getfield)) {
            $getfields = str_replace('?', '', explode('&', $getfield));
            foreach ($getfields as $g) {
                $split = explode('=', $g);
                /** In case a null is passed through **/
                if (isset($split[1])) {
                    $oauth[$split[0]] = urldecode($split[1]);
                }
            }
        }
        $postfields = $this->getPostfields();
        if (!is_null($postfields)) {
            foreach ($postfields as $key => $value) {
                $oauth[$key] = $value;
            }
        }
        $base_info = $this->buildBaseString($url, $requestMethod, $oauth);
        $composite_key = rawurlencode($consumer_secret) . '&' . rawurlencode($oauth_access_token_secret);
        $oauth_signature = base64_encode(hash_hmac('sha1', $base_info, $composite_key, true));
        $oauth['oauth_signature'] = $oauth_signature;
        $this->url = $url;
        $this->requestMethod = $requestMethod;
        $this->oauth = $oauth;
        return $this;
    }

    /**
     * Perform the actual data retrieval from the API
     *
     * @param boolean $return If true, returns data. This is left in for backward compatibility reasons
     * @param array $curlOptions Additional Curl options for this request
     *
     * @return string json If $return param is true, returns json data.
     * @throws \Exception
     *
     */
    public function performRequest($return = true, $curlOptions = array())
    {
        if (!is_bool($return)) {
            throw new Exception('performRequest parameter must be true or false');
        }
        $header = array($this->buildAuthorizationHeader($this->oauth), 'Expect:');
        $getfield = $this->getGetfield();
        $postfields = $this->getPostfields();
        if (in_array(strtolower($this->requestMethod), array('put', 'delete'))) {
            $curlOptions[CURLOPT_CUSTOMREQUEST] = $this->requestMethod;
        }
        $options = $curlOptions + array(
                CURLOPT_HTTPHEADER => $header,
                CURLOPT_HEADER => false,
                CURLOPT_URL => $this->url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            );
        if (!is_null($postfields)) {
            $options[CURLOPT_POSTFIELDS] = http_build_query($postfields, '', '&');
        } else {
            if ($getfield !== '') {
                $options[CURLOPT_URL] .= $getfield;
            }
        }
        $feed = curl_init();
        curl_setopt_array($feed, $options);
        $json = curl_exec($feed);
        $this->httpStatusCode = curl_getinfo($feed, CURLINFO_HTTP_CODE);
        if (($error = curl_error($feed)) !== '') {
            curl_close($feed);
            throw new \Exception($error);
        }
        curl_close($feed);
        return $json;
    }

    /**
     * Private method to generate the base string used by cURL
     *
     * @param string $baseURI
     * @param string $method
     * @param array $params
     *
     * @return string Built base string
     */
    private function buildBaseString($baseURI, $method, $params)
    {
        $return = array();
        ksort($params);
        foreach ($params as $key => $value) {
            $return[] = rawurlencode($key) . '=' . rawurlencode($value);
        }
        return $method . "&" . rawurlencode($baseURI) . '&' . rawurlencode(implode('&', $return));
    }

    /**
     * Private method to generate authorization header used by cURL
     *
     * @param array $oauth Array of oauth data generated by buildOauth()
     *
     * @return string $return Header used by cURL for request
     */
    private function buildAuthorizationHeader(array $oauth)
    {
        $return = 'Authorization: OAuth ';
        $values = array();
        foreach ($oauth as $key => $value) {
            if (in_array($key, array('oauth_consumer_key', 'oauth_nonce', 'oauth_signature',
                'oauth_signature_method', 'oauth_timestamp', 'oauth_token', 'oauth_version'))) {
                $values[] = "$key=\"" . rawurlencode($value) . "\"";
            }
        }
        $return .= implode(', ', $values);
        return $return;
    }

    /**
     * Helper method to perform our request
     *
     * @param string $url
     * @param string $method
     * @param string $data
     * @param array $curlOptions
     *
     * @return string The json response from the server
     * @throws \Exception
     *
     */
    public function request($url, $method = 'get', $data = null, $curlOptions = array())
    {
        if (strtolower($method) === 'get') {
            $this->setGetfield($data);
        } else {
            $this->setPostfields($data);
        }
        return $this->buildOauth($url, $method)->performRequest(true, $curlOptions);
    }

    /**
     * Get the HTTP status code for the previous request
     *
     * @return integer
     */
    public function getHttpStatusCode()
    {
        return $this->httpStatusCode;
    }
}



function getTweetHtml($thisTweet){
    $mediaHtml = '';
    $mediaPrefix = '';
    $mediaPostfix = '';
    if($thisTweet['has_media']) {
        for ($i = 1; $i <= 4; $i++) {
            if(isset($thisTweet['media_' . $i . '_type'])) {
                if ($thisTweet['media_' . $i . '_type']=='photo') {
                    if($i==1) {
                        $mediaPrefix .= '<div class="with_media">';
                        $mediaPostfix .= '</div>';
                    }
                    $mediaHtml .= <<<HTML
    <div class="paddingBottom10 media-wrap">
        <a href="{$thisTweet['media_' . $i . '_mediaUrl']}">
            <div class="relative inline-block"><img src="{$thisTweet['media_' . $i . '_mediaUrl']}" alt="{$thisTweet['media_' . $i . '_type']}" style="width:100%;max-width:{$thisTweet['media_' . $i . '_w']}px;max-height:{$thisTweet['media_' . $i . '_h']}px"/>
            </div>
        </a>
    </div>
HTML;
                } else if($thisTweet['media_' . $i . '_type']=='video'){
                    $mediaHtml .= <<<HTML
    <div class="paddingBottom10 media-wrap">
        <a href="{$thisTweet['media_' . $i . '_videoUrl']}" class="video_media">
            <div class="relative inline-block"><img src="{$thisTweet['media_' . $i . '_mediaUrl']}" alt="{$thisTweet['media_' . $i . '_type']}" style="width:100%;max-width:{$thisTweet['media_' . $i . '_w']}px;max-height:{$thisTweet['media_' . $i . '_h']}px"/>
                <div class="video-play"><span class="font-size-25">▶</span>︎</div>
            </div>
        </a>
    </div>
HTML;
                }

            }
        }
        $mediaHtml = $mediaPrefix . $mediaHtml . $mediaPostfix;
    }

    $replyHtml = '';
    if(isset($thisTweet['is_reply']) && $thisTweet['is_reply']){
        $replyHtml .= <<<HTML
<div style="background-color:#e6e6e6;border-radius:5px" class="marginBottom15 paddingTop5 paddingBottom5 paddingLeft10 paddingRight10 inline-block"><a href="{$thisTweet['link_to_tweet']}" target="_blank"><small class="grey">回覆 </small><small class="grey">@{$thisTweet['reply_screen_name']}</small></a></div>
HTML;
    }
    $quoteHtml = '';
    if(isset($thisTweet['is_quote']) && $thisTweet['is_quote']){
        $quoteHtml .= <<<HTML
<div style="background-color:#e6e6e6;border-radius:5px" class="marginBottom15 paddingTop5 paddingBottom5 paddingLeft10 paddingRight10 inline-block"><a href="{$thisTweet['link_to_tweet']}" target="_blank"><small class="grey">引用 </small><small class="grey">@{$thisTweet['quote_screen_name']}</small></a></div>
HTML;
    }

    $timeAgo = time_elapsed_string($thisTweet['time_string']);
    $favouriteCountFormatted = number_format($thisTweet['favorite_count'], 0, '', ',');
    $retweetCountFormatted = number_format($thisTweet['retweet_count'], 0, '', ',');
    $parsedText = nl2br($thisTweet['text']);
    $root = '';
    $tweetHtml = <<<HTML
<div class="tweet-container">
    {$replyHtml}
    <div class="paddingBottom20 nowrap">
        <div class="inline-block marginRight10 twi-top-box-1">
            <div class="twi_avatar"><img src="{$thisTweet['user_profile_image_url']}" class="" alt="{$thisTweet['user_screen_name']}"/></div>
        </div>
        <div class="inline-block twi-top-box-2">
            <div><span>{$thisTweet['user_name']}</span></div>
            <div>
                <div class="inline-block"><a href="{$thisTweet['link_to_tweet']}" target="_blank">
                    <small data-t="{$thisTweet['time_string']}">{$timeAgo}</small>
                </a></div>
                <div class="inline-block"><span class="dot-sep"></span></div>
                <div class="inline-block"><a href="https://twitter.com/{$thisTweet['user_screen_name']}" target="_blank">
                    <small>@{$thisTweet['user_screen_name']}</small>
                </a>
                </div>
            </div>
        </div>
    </div>
    <div><p class="tweet-text">{$parsedText}</p></div>
    {$quoteHtml}
    <div class="paddingBottom10">
        <div class="inline-block marginRight20">
            <span class="stat-tag">RT</span><br/>
            <span class="stat-num">{$retweetCountFormatted}</span>
        </div>
        <div class="inline-block marginRight20">
            <span class="stat-tag">LIKE</span><br/>
            <span class="stat-num">{$favouriteCountFormatted}</span>
        </div>
    </div>
    <div class="paddingBottom10">{$mediaHtml}</div>
    <div class="paddingTop20">
    <div class="clearfix"></div>
    </div>
</div>
HTML;
    return $tweetHtml;
}


// https://stackoverflow.com/questions/1416697/converting-timestamp-to-time-ago-in-php-e-g-1-day-ago-2-days-ago
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'years',
        'm' => 'months',
        'w' => 'weeks',
        'd' => 'days',
        'h' => 'hours',
        'i' => 'minutes',
        's' => 'seconds',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? '' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

function parseNewTweet($tweetData){
    /* parse data */
    $tweetIdStr = $tweetData["id_str"];

    $rawTime = $tweetData["created_at"];
    $timeString = date("Y-m-d H:i:s",strtotime($rawTime));

    $rawText = (isset($tweetData["full_text"]) ? $tweetData["full_text"] : $tweetData["text"]);
    $outputText = preg_replace("/(\s(https?:\/\/)?(pic\.twitter\.com|twitter\.com|t\.co)\/?[^:]*)$/"," ",$rawText);
    $char_count = mb_strlen($outputText,'UTF-8');

    $favorite_count = $tweetData["favorite_count"];
    $retweet_count = $tweetData["retweet_count"];
    $userScreenName = $tweetData["user"]["screen_name"];

    // create new db entry
    $output = Array(
        "tweet_id" => $tweetIdStr,
        "text" => $outputText,
        "char_count"=>$char_count,
        "link_to_tweet" => "https://twitter.com/".$userScreenName."/status/".$tweetIdStr,
        "favorite_count" => $favorite_count,
        "retweet_count" => $retweet_count,
        "time_string" => $timeString,
        "user_name" => $tweetData["user"]["name"],
        "user_screen_name" => $userScreenName,
        "user_profile_image_url" => $tweetData["user"]["profile_image_url_https"],

        "was_blacklist" => 0,
        "ng_score" => 0,
        "is_reply" => 0,
        "reply_status_id" => null,
        "reply_screen_name" => null,

        "is_quote" => 0,
        "quote_screen_name" => null,
    );

    $output["has_media"]=0;
    if(isset($tweetData["extended_entities"])){
        $count=1;
        foreach ($tweetData["extended_entities"]["media"] as $thisMediaItem){
            $output["has_media"]=1;

            $thisType = $thisMediaItem["type"];
            $output["media_".$count."_type"]=$thisType;
            $output["media_".$count."_displayUrl"]=$thisMediaItem["expanded_url"];
            $output["media_".$count."_w"]=$thisMediaItem["sizes"]["small"]["w"];
            $output["media_".$count."_h"]=$thisMediaItem["sizes"]["small"]["h"];
            $output["media_".$count."_mediaUrl"]=$thisMediaItem["media_url_https"];
            if($thisType==='video'){
                $thisVideo = null;
                foreach($thisMediaItem["video_info"]["variants"] as $thisVariant){
                    // HD variants seem to come first
                    if($thisVariant["content_type"]=="video/mp4"){
                        $thisVideo = $thisVariant["url"];
                        break;
                    }
                }
                $output["media_".$count."_videoUrl"] = $thisVideo;
            }
            $count++;
        }
    }
    return $output;

}

$url = 'https://api.twitter.com/1.1/statuses/user_timeline.json';
$getfield = '?screen_name='.$_GET['screen_name'].'&tweet_mode=extended&count=10';
// &exclude=hashtags
$requestMethod = 'GET';

$twitter = new TwitterAPIExchange($twitter_settings);
$res = $twitter->setGetfield($getfield)
    ->buildOauth($url, $requestMethod)
    ->performRequest();

$twi_data = json_decode($res, true);

$outputArr = [];

foreach($twi_data as $thisHot) {
    $outputArr[] = getTweetHtml(parseNewTweet($thisHot));
}

echo json_encode($outputArr);
exit;
