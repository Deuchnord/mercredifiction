<?php
/**
 * Created by PhpStorm.
 * User: jerome
 * Date: 19/06/2018
 * Time: 12:59.
 */

namespace App\Utils;

use App\Entity\Author;
use App\Entity\Status;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class MastodonUtils
{
    public const VISIBILITY_DIRECT = 'direct';
    public const VISIBILITY_PRIVATE = 'private';
    public const VISIBILITY_UNLISTED = 'unlisted';
    public const VISIBILITY_PUBLIC = 'public';

    /**
     * Get the last mentions received by the bot.
     *
     * @param int $sinceId the last status ID (excluded) to fetch. Usually the greatest status ID in the database.
     *
     * @return Status[]
     *
     * @throws \Exception
     */
    public static function getLastMentions(int $sinceId = -1)
    {
        $token = self::getToken();
        $url = getenv('MASTODON_INSTANCE').'/api/v1/notifications';

        $options = [
            'exclude_types' => ['follow', 'favourite', 'reblog'],
        ];

        if (-1 != $sinceId) {
            $options['since_id'] = $sinceId;
        }

        return self::fetchStatuses($url, $options, $token, null, null, true);
    }

    /**
     * Get the last statuses from the Home timeline.
     *
     * @param Author[] $authorizedAuthors the authors whose statuses can be retrieved
     * @param int      $sinceId           the last status ID (excluded) to fetch. Usually the greatest status ID in the database.
     *
     * @return Status[]
     *
     * @throws \Exception
     */
    public static function getLastStatuses(array $authorizedAuthors, int $sinceId = -1)
    {
        $hashtag = '#'.getenv('HASHTAG');
        $token = self::getToken();
        $url = getenv('MASTODON_INSTANCE').'/api/v1/timelines/home';

        return self::fetchStatuses($url, ['since_id' => $sinceId], $token, $hashtag, $authorizedAuthors);
    }

    /**
     * Get the author's statuses that contain the hashtag given in the .env file.
     *
     * @param Author $author the author for which the statuses are wanted
     * @param Status $startFromStatus if set, start from that status
     * @return Status[] an array of statuses
     *
     * @throws \Exception
     */
    public static function getAuthorStatuses(Author $author, Status $startFromStatus = null) {
        $hashtag = getenv('HASHTAG');
        $token = self::getToken();

        $url = getenv('MASTODON_INSTANCE').'/api/v1/accounts/'.$author->getIdMastodon().'/statuses';

        $getOptions = [];

        if($startFromStatus != null) {
            $getOptions['since_id'] = $startFromStatus->getIdMastodon();
        }

        $statuses = self::fetchStatuses($url, $getOptions, $token, $hashtag);

        return $statuses;
    }

    /**
     * Refreshes the token to access Mastodon's API.
     *
     * @return string the token
     *
     * @throws \Exception
     */
    private static function getToken(): string
    {
        if ($devToken = getenv('TOKEN')) {
            return $devToken;
        }

        throw new \Exception('Please provide a token in the .env file!');
    }

    /**
     * Sends a request to the given URL.
     *
     * @param string $url    the URL to call
     * @param bool   $isPost if true, the request will be made with the HTTP POST method instead of GET
     * @param array  $body   the content of the body of the request. Ignored if $isPost == false
     * @param string $bearer a token, if required by the distant URL
     * @param string $file   the path to the file to upload
     *
     * @return array|false an associative array('header', 'body') that contains the header and the body of the response
     *
     * @throws \Exception    thrown if the request has failed
     * @throws FileException thrown if an error occurs with the file
     */
    private static function sendRequest(string $url, bool $isPost, array $body = [], string $bearer = null, string $file = null)
    {
        $request = curl_init($url);

        $header = [];

        if (null !== $bearer) {
            $header[] = 'Authorization: Bearer '.$bearer;
        }

        if (empty($header)) {
            $header = false;
        }

        if ($isPost) {
            curl_setopt($request, CURLOPT_POST, 1);

            if (null != $file) {
                $body['file'] = new \CURLFile($file);
            }

            $postStr = http_build_query($body, '', '&');
            curl_setopt($request, CURLOPT_POSTFIELDS, $postStr);
        }

        if (false !== $header) {
            curl_setopt($request, CURLOPT_HEADER, true);
            curl_setopt($request, CURLOPT_HTTPHEADER, $header);
        }

        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);

        $str = curl_exec($request);

        if (false === $str) {
            throw new \Exception(curl_error($request));
        }

        curl_close($request);

        return self::separateHeaders($str);
    }

    /**
     * Checks if given $authorId is in $authorizedAuthors.
     *
     * @param Author[] $authorizedAuthors an array of Author of authorized authors
     * @param int      $authorId          an author's ID
     *
     * @return bool true if the given author is in the array
     */
    private static function isAuthorAuthorized(array $authorizedAuthors, int $authorId): bool
    {
        foreach ($authorizedAuthors as $author) {
            if ($authorId == $author->getIdMastodon()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if given $toot contains given $hashtag.
     *
     * @param string $hashtag the hashtag to look for
     * @param object $toot    an object that represents the status as given by Mastodon's API
     *
     * @return bool true if and only if the hashtag is present in the toot. If $hashtag == null, returns false.
     */
    private static function hasHashtag(string $hashtag, $toot)
    {
        if (null == $hashtag) {
            return false;
        }

        $hashtag = str_replace('#', '', $hashtag);

        foreach ($toot->tags as $tag) {
            if ($tag->name == strtolower($hashtag)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fetches the statuses at the given $url.
     *
     * @param string      $url               the URL to call in order to get the statuses
     * @param array|null  $getOptions        the GET options to append to the URL - see Mastodon's API documentation for more details
     * @param string|null $token             the token to use in order to fetch the API
     * @param string|null $hashtag           a hashtag to filter on
     * @param array|null  $authorizedAuthors the authors to filter on
     * @param bool        $isEncapsulated    if true, the statuses will be considered as encapsulated in objects that contain an ID and a status
     *
     * @return Status[]|array an array of status, or, if $isEncapsulated == true, a ["idNotification", "status"] array
     *
     * @throws \Exception
     */
    private static function fetchStatuses(string $url, array $getOptions = [], string $token = null, string $hashtag = null, array $authorizedAuthors = null, $isEncapsulated = false): array
    {
        $statuses = [];
        $urlToCall = $url;
        $sep = '?';

        if (null === $getOptions) {
            $getOptions = [];
        }

        foreach ($getOptions as $opt => $val) {
            if (!is_array($val)) {
                $urlToCall .= $sep.$opt.'='.urlencode($val);
            } else {
                foreach ($val as $v) {
                    $urlToCall .= $sep.$opt.'[]='.urlencode($v);

                    if ('?' == $sep) {
                        $sep = '&';
                    }
                }
            }

            if ('?' == $sep) {
                $sep = '&';
            }
        }

        $response = self::sendRequest($urlToCall, false, [], $token);
        $toots = json_decode($response['body']);

        if (isset($toots->error)) {
            throw new \Exception($toots->error);
        }

        if ($toots == []) {
            return [];
        }

        $maxId = -1;

        foreach ($toots as $toot) {
            $idNotification = 0;

            if (-1 == $maxId || $toot->id < $maxId) {
                $maxId = $toot->id;
            }

            if ($isEncapsulated) {
                $idNotification = $toot->id;
                $toot = $toot->status;
            }

            if ((null == $hashtag || self::hasHashtag($hashtag, $toot)) && (null == $authorizedAuthors || self::isAuthorAuthorized($authorizedAuthors, $toot->account->id))) {
                // Clean the toot
                $content = preg_replace("#<br[ ]?/?>#", "\n", $toot->content);
                $content = str_replace('</p><p>', "\n", $content);
                $content = strip_tags($content);

                $status = new Status();

                $author = new Author();
                $author->setUsername($toot->account->acct)
                    ->setIdMastodon($toot->account->id)
                    ->setAvatar($toot->account->avatar_static)
                    ->setDisplayName($toot->account->display_name);

                $status->setIdMastodon($toot->id)
                    ->setAuthor($author)
                    ->setDate(new \DateTime($toot->created_at))
                    ->setUrl($toot->url)
                    ->setBlacklisted(false)
                    ->setContent($content);

                if ($isEncapsulated) {
                    $statuses[] = [
                        'idNotification' => $idNotification,
                        'status' => $status,
                    ];
                } else {
                    $statuses[] = $status;
                }
            }
        }

        $options = $getOptions;
        $options['max_id'] = $maxId - 1;

        return array_merge($statuses, self::fetchStatuses($url, $options, $token, $hashtag, $authorizedAuthors, $isEncapsulated));
    }

    private static function separateHeaders($str)
    {
        $arr = preg_split("#\r\n\r\n#", $str);
        $body = $arr[1];
        $headers = [];

        foreach (preg_split("#\r\n#", $arr[0]) as $header) {
            if (preg_match('#^HTTP/1\\.#', $header)) {
                $header = 'Status: '.$header;
            }

            $h = preg_split('#: ?#', $header, 2);
            $headers[$h[0]] = $h[1];
        }

        return ['headers' => $headers, 'body' => $body];
    }

    /**
     * Sens a new status.
     *
     * @param string $message      the message to send - the mention is automatically added at the beginning of the message
     * @param Status $inResponseTo the status you want to reply to, or null
     * @param string $visibility   one of the values accepted by the API (see the VISIBILITY_* constants)
     *
     * @throws \Exception
     *
     * @see VISIBILITY_DIRECT (default value): the new status will only be visible by the bot's account and the mentionned accounts
     * @see VISIBILITY_PRIVATE: the new status will only be visible by the accounts that follow the bot's account
     * @see VISIBILITY_UNLISTED: the new status will be visible in all timelines, but will not be shown on the bot's profile
     * @see VISIBILITY_PUBLIC: the new status will be visible in all timelines and on the bot's profile
     */
    public static function sendStatus(string $message, Status $inResponseTo = null, string $visibility = self::VISIBILITY_DIRECT)
    {
        $data = [
            'status' => '@'.$inResponseTo->getAuthor()->getUsername().' '.$message,
            'in_reply_to_id' => $inResponseTo->getIdMastodon(),
            'visibility' => $visibility,
        ];

        self::sendRequest(getenv('MASTODON_INSTANCE').'/api/v1/statuses', true, $data, self::getToken());
    }

    /**
     * Fetches the author in.
     *
     * @param int $idMastodon
     *
     * @return Author
     *
     * @throws \Exception
     */
    public static function getAuthor(int $idMastodon): Author
    {
        $url = getenv('MASTODON_INSTANCE').'/api/v1/accounts/'.$idMastodon;
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

    /**
     * Follows the given account on Mastodon.
     * This is mandatory in order to make the getLastStatuses() work correctly, as this method uses the Home timeline to
     * get the known authors' statuses.
     *
     * @param Author $author the account to follow
     *
     * @throws \Exception
     */
    public static function follow(Author $author)
    {
        $url = getenv('MASTODON_INSTANCE').'/api/v1/accounts/'.$author->getIdMastodon().'/follow';
        $body = [
            'reblogs' => false, // Tell Mastodon to not show the account's reblogs in the timeline
        ];
        $token = self::getToken();

        self::sendRequest($url, true, $body, $token);
    }

    /**
     * Unfollows the given account on Mastodon.
     *
     * @param Author $author
     *
     * @throws \Exception
     */
    public static function unfollow(Author $author)
    {
        $url = getenv('MASTODON_INSTANCE').'/api/v1/accounts/'.$author->getIdMastodon().'/unfollow';
        $token = self::getToken();

        self::sendRequest($url, true, [], $token);
    }
}
