<?php

/**
 * @see https://developers.podio.com/doc/stream
 */
class PodioStreamObject extends PodioObject
{
    public function __construct($podio, $attributes = array())
    {
        parent::__construct($podio);
        $this->property('id', 'integer');
        $this->property('type', 'string');
        $this->property('last_update_on', 'datetime');
        $this->property('title', 'string');
        $this->property('link', 'string');
        $this->property('rights', 'array');
        $this->property('data', 'hash');
        $this->property('comments_allowed', 'boolean');
        $this->property('user_ratings', 'hash');
        $this->property('created_on', 'datetime');

        $this->has_one('created_by', 'ByLine');
        $this->has_one('created_via', 'Via');
        $this->has_one('app', 'App');
        $this->has_one('space', 'Space');
        $this->has_one('organization', 'Organization');

        $this->has_many('comments', 'Comment');
        $this->has_many('files', 'File');
        $this->has_many('activity', 'Activity');

        $this->init($attributes);
    }

    /**
     * @see https://developers.podio.com/doc/stream/get-global-stream-80012
     */
    public function get($attributes = array())
    {
        return $this->listing($this->podio->get("/stream/", $attributes));
    }

    /**
     * @see https://developers.podio.com/doc/stream/get-organization-stream-80038
     */
    public function get_for_org($org_id, $attributes = array())
    {
        return $this->listing($this->podio->get("/stream/org/{$org_id}/", $attributes));
    }

    /**
     * @see https://developers.podio.com/doc/stream/get-space-stream-80039
     */
    public function get_for_space($space_id, $attributes = array())
    {
        return $this->listing($this->podio->get("/stream/space/{$space_id}/", $attributes));
    }

    /**
     * @see https://developers.podio.com/doc/stream/get-app-stream-264673
     */
    public function get_for_app($app_id, $attributes = array())
    {
        return $this->listing($this->podio->get("/stream/app/{$app_id}/", $attributes));
    }

    /**
     * @see https://developers.podio.com/doc/stream/get-user-stream-1289318
     */
    public function get_for_user($user_id, $attributes = array())
    {
        return $this->listing($this->podio->get("/stream/user/{$user_id}/", $attributes));
    }

    /**
     * @see https://developers.podio.com/doc/stream/get-app-stream-264673
     */
    public function personal($attributes = array())
    {
        return $this->listing($this->podio->get("/stream/personal/", $attributes));
    }

}
