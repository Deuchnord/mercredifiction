<?php
/**
 * Created by PhpStorm.
 * User: jerome
 * Date: 19/06/2018
 * Time: 12:59
 */

namespace App\Utils;

use App\Entity\Author;
use App\Entity\Status;

class MastodonUtils {

    public const VISIBILITY_DIRECT = 'direct';
    public const VISIBILITY_PRIVATE = 'private';
    public const VISIBILITY_UNLISTED = 'unlisted';
    public const VISIBILITY_PUBLIC = 'public';

    /**
     * Get the last mentions received by the bot
     * @param int $sinceId the last status ID (excluded) to fetch. Usually the greatest status ID in the database.
     * @return Status[]
     * @throws \Exception
     */
    public static function getLastMentions(int $sinceId = -1) {
        $token = self::getToken();
        $url = getenv('MASTODON_INSTANCE') . '/api/v1/notifications?exclude_types[]=follow&exclude_types[]=favourite&exclude_types[]=reblog&since_id=' . $sinceId;

        return self::getStatuses($url, -1, $token, null, null, true);
    }

    /**
     * Get the last statuses from the Home timeline
     * @param Author[] $authorizedAuthors the authors whose statuses can be retrieved
     * @param int $endId the last status ID (excluded) to fetch. Usually the greatest status ID in the database.
     * @return Status[]
     * @throws \Exception
     */
    public static function getLastStatuses(array $authorizedAuthors, int $endId = -1) {
        $hashtag = '#' . getenv('HASHTAG');
        $token = self::getToken();
        $url = getenv('MASTODON_INSTANCE') . '/api/v1/timelines/home';

        return self::getStatuses($url, $endId, $token, $hashtag, $authorizedAuthors);
    }

    /**
     * Get the author's statuses that contain the hashtag given in the .env file
     * @param Author $author the author for which the statuses are wanted
     * @return Status[] an array of statuses
     * @throws \Exception
     * @return Status[]
     */
    public static function getAuthorStatuses(Author $author) {
        $hashtag = getenv('HASHTAG');
        $token = self::getToken();

        $url = getenv('MASTODON_INSTANCE') . '/api/v1/accounts/' . $author->getIdMastodon() . '/statuses';

        $statuses = self::getStatuses($url, -1, $token, $hashtag);

        return $statuses;
    }

    /**
     * Refreshes the token to access Mastodon's API
     * @return string the token
     * @throws \Exception
     */
    private static function getToken(): string {
        if($devToken = getenv('TOKEN')) {
            return $devToken;
        }

        $appId = getenv('APP_ID');
        $appSecret = getenv('APP_SECRET');
        $url = getenv('MASTODON_INSTANCE') . '/oauth/token';
        $json = MastodonUtils::sendRequest($url, true, ['client_id' => $appId, 'client_secret' => $appSecret]);
        $data = json_decode($json, true);

        return $data['access_token'];
    }

    /**
     * Sends a request to the given URL
     * @param string $url the URL to call
     * @param bool $isPost if true, the request will be made with the HTTP POST method instead of GET
     * @param array $body the content of the body of the request. Ignored if $isPost == false
     * @param string $bearer a token, if required by the distant URL
     * @return array|false an associative array('header', 'body') that contains the header and the body of the response
     * @throws \Exception thrown if the request has failed
     */
    private static function sendRequest(string $url, bool $isPost, array $body = [], string $bearer = null) {
        $request = curl_init($url);

        $header = [];

        if($bearer !== null) {
            $header[] = 'Authorization: Bearer ' . $bearer;
        }

        if(empty($header)) {
            $header = false;
        }

        if($isPost) {
            curl_setopt($request, CURLOPT_POST, 1);
            $postStr = http_build_query($body, '', '&');
            curl_setopt($request, CURLOPT_POSTFIELDS, $postStr);
        }

        if($header !== false) {
            curl_setopt($request, CURLOPT_HEADER, true);
            curl_setopt($request, CURLOPT_HTTPHEADER, $header);
        }

        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);

        $str = curl_exec($request);

        if($str === false) {
            throw new \Exception(curl_error($request));
        }

        curl_close($request);

        return self::separateHeaders($str);
    }

    /**
     * Checks if given $authorId is in $authorizedAuthors.
     * @param Author[] $authorizedAuthors an array of Author of authorized authors
     * @param int $authorId an author's ID
     * @return bool true if the given author is in the array.
     */
    private static function isAuthorAuthorized(array $authorizedAuthors, int $authorId): bool {
        foreach($authorizedAuthors as $author) {
            if($authorId == $author->getMastodonId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if given $toot contains given $hashtag
     * @param string $hashtag the hashtag to look for
     * @param object $toot an object that represents the status as given by Mastodon's API
     * @return bool true if and only if the hashtag is present in the toot. If $hashtag == null, returns false.
     */
    private static function hasHashtag(string $hashtag, $toot) {
        if($hashtag == null) {
            return false;
        }

        foreach($toot->tags as $tag) {
            if($tag->name == strtolower($hashtag)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fetches the author with given $mastodonId from $authorizedAuthors
     * @param Author[] $authorizedAuthors
     * @param int $mastodonId
     * @return Author|null
     */
    private static function getAuthorByMastodonId(array $authorizedAuthors, int $mastodonId) {
        foreach($authorizedAuthors as $author) {
            if($author->getIdMastodon() == $mastodonId) {
                return $author;
            }
        }

        return null;
    }

    /**
     * Gets all the statuses from given URL
     * @param string $url the URL to call in order to get the statuses
     * @param int $endId the maximum ID (excluded) of the statuses - ignored if negative
     * @param string|null $token if defined, the authentication token to give to the API
     * @param string|null $hashtag the hashtag to filter on
     * @param array|null $authorizedAuthors the authors whose statuses can be returned by this method
     * @param bool $isEncapsulated if true, consider that the array received by the $url does not contain statuses but objects that contains a field `status`
     * @return Status[] the statuses returned by the $url
     * @throws \Exception
     */
    private static function getStatuses(string $url, int $endId = -1, string $token = null, string $hashtag = null, array $authorizedAuthors = null, $isEncapsulated = false) {
        $statuses = [];
        $sinceId = $endId;

        $maxIdHttpParameterSeparator = (strstr($url, '?')) ? '&' : '?';

        while (true) {
            $urlToCall = $url . (($sinceId > 0) ? ($maxIdHttpParameterSeparator . 'max_id=' . $sinceId) : '');
            $response = self::sendRequest($urlToCall, false, [], $token);

            if ($response === false) {
                throw new \Exception("An error occurred while retrieving the statuses");
            }

            $toots = json_decode($response['body']);

            if(empty($toots)) {
                break;
            }

            if($toots === null) {
                throw new \Exception("Server responded NULL!");
            }

            if(isset($toots->error)) {
                throw new \Exception($toots->error);
            }

            foreach ($toots as $t) {
                $toot = $t;

                if($isEncapsulated) {
                    $toot = $t->status;
                }

                if ($authorizedAuthors != null && !self::isAuthorAuthorized($authorizedAuthors, $toot->account->id) ||
                        $hashtag != null && !self::hasHashtag($hashtag, $toot)) {
                    // Ignore any toots from unauthorized authors or not having the $hashtag.
                    $sinceId = $toot->id;
                    continue;
                }

                $content = preg_replace("#<br ?/?>#", "\n", $toot->content);
                $content = str_replace('</p><p>', "\n", $content);
                $content = strip_tags($content);

                $status = new Status();
                $status->setIdMastodon($toot->id)
                    ->setDate(new \DateTime($toot->created_at))
                    ->setBlacklisted(false);

                if($authorizedAuthors !== null) {
                    $status->setAuthor(self::getAuthorByMastodonId($authorizedAuthors, $toot->account->id));
                } else {
                    $author = new Author();
                    $author->setUsername($toot->account->acct)
                        ->setIdMastodon($toot->account->id)
                        ->setAvatar($toot->account->avatar_static)
                        ->setDisplayName($toot->account->display_name);

                    $status->setAuthor($author);
                }

                $status->setUrl($toot->url)
                    ->setContent($content);

                if($isEncapsulated) {
                    $status->setIdMastodon($t->id);
                }

                $sinceId = $status->getIdMastodon();

                $statuses[] = $status;
            }
        }
        return $statuses;
    }

    private static function separateHeaders($str) {
        $arr = preg_split("#\r\n\r\n#", $str);
        $body = $arr[1];
        $headers = [];

        foreach(preg_split("#\r\n#", $arr[0]) as $header) {
            if(preg_match('#^HTTP/1\\.#', $header)) {
                $header = 'Status: ' . $header;
            }

            $h = preg_split('#: ?#', $header, 2);
            $headers[$h[0]] = $h[1];
        }

        return ['headers' => $headers, 'body' => $body];
    }

    /**
     * Sens a new status
     * @param string $message the message to send - the mention is automatically added at the beginning of the message
     * @param Status $inResponseTo the status you want to reply to, or null
     * @param string $visibility one of the values accepted by the API (see the API_* constants)
     * @throws \Exception
     */
    public static function sendStatus(string $message, Status $inResponseTo = null, string $visibility = self::VISIBILITY_DIRECT) {
        $data = [
            'status' => '@' . $inResponseTo->getAuthor()->getUsername() . ' ' . $message,
            'in_reply_to_id' => $inResponseTo->getIdMastodon(),
            'visibility' => $visibility
        ];

        self::sendRequest(getenv('MASTODON_INSTANCE') . '/api/v1/statuses', true, $data, self::getToken());
    }

    /**
     * Fetches the author in 
     * @param int $idMastodon
     * @return Author
     * @throws \Exception
     */
    public static function getAuthor(int $idMastodon): Author {
        $url = getenv('MASTODON_INSTANCE') . '/api/v1/accounts/' . $idMastodon;
        $bearer = self::getToken();
        $response = self::sendRequest($url, false, [], $bearer);

        $auth = json_decode($response['body']);

        $author = new Author();
        $author->setIdMastodon($idMastodon)
            ->setUsername($auth->acct)
            ->setDisplayName($auth->display_name)
            ->setAvatar($auth->avatar);

        return $author;
    }
}