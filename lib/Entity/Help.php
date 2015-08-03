<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Help.php)
 */


namespace Xibo\Entity;
use Respect\Validation\Validator as v;
use Xibo\Storage\PDOConnect;

/**
 * Class Help
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class Help
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The ID of this Help Record")
     * @var int
     */
    public $helpId;

    /**
     * @SWG\Property(description="The topic for this Help Record")
     * @var string
     */
    public $topic;

    /**
     * @SWG\Property(description="The Category for this Help Record")
     * @var string
     */
    public $category;

    /**
     * @SWG\Property(description="The Link to the Manual for this Help Record")
     * @var string
     */
    public $link;

    public function getId()
    {
        return $this->helpId;
    }

    public function getOwnerId()
    {
        return 1;
    }

    public function validate()
    {
        if (!v::string()->notEmpty()->length(1, 254)->validate($this->topic))
            throw new \InvalidArgumentException(__('Topic is a required field. It must be between 1 and 254 characters.'));

        if (!v::string()->notEmpty()->length(1, 254)->validate($this->category))
            throw new \InvalidArgumentException(__('Category is a required field. It must be between 1 and 254 characters.'));

        if (!v::string()->notEmpty()->length(1, 254)->validate($this->link))
            throw new \InvalidArgumentException(__('Link is a required field. It must be between 1 and 254 characters.'));
    }

    public function save($validate = true)
    {
        if ($validate)
            $this->validate();

        if ($this->helpId == null || $this->helpId == 0)
            $this->add();
        else
            $this->edit();
    }

    public function delete()
    {
        PDOConnect::update('DELETE FROM `help` WHERE HelpID = :helpid', [
            'helpId' => $this->helpId
        ]);
    }

    private function add()
    {
        $this->helpId = PDOConnect::insert('INSERT INTO `help` (Topic, Category, Link) VALUES (:topic, :category, :link)', [
            'topic' => $this->topic,
            'category' => $this->category,
            'link' => $this->link
        ]);
    }

    private function edit()
    {
        PDOConnect::update('UPDATE `help` SET Topic = :topic, Category = :category, Link = :link WHERE HelpID = :helpid', [
            'helpId' => $this->helpId,
            'topic' => $this->topic,
            'category' => $this->category,
            'link' => $this->link
        ]);
    }
}