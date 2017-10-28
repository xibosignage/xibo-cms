<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (TransitionFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\Transition;
use Xibo\Exception\NotFoundException;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class TransitionFactory
 * @package Xibo\Factory
 */
class TransitionFactory extends BaseFactory
{
    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     */
    public function __construct($store, $log, $sanitizerService)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
    }

    /**
     * @return Transition
     */
    public function createEmpty()
    {
        return new Transition($this->getStore(), $this->getLog());
    }

    /**
     * @param int $transitionId
     * @return Transition
     * @throws NotFoundException
     */
    public function getById($transitionId)
    {
        $transitions = $this->query(null, ['transitionId' => $transitionId]);

        if (count($transitions) <= 0)
            throw new NotFoundException();

        return $transitions[0];
    }

    /**
     * Get by Code
     * @param string $code
     * @return Transition
     * @throws NotFoundException
     */
    public function getByCode($code)
    {
        $transitions = $this->query(null, ['code' => $code]);

        if (count($transitions) <= 0)
            throw new NotFoundException();

        return $transitions[0];
    }

    /**
     * Get enabled by type
     * @param string $type
     * @return array[Transition]
     */
    public function getEnabledByType($type)
    {
        $filter = [];

        if ($type == 'in') {
            $filter['availableAsIn'] = 1;
        } else {
            $filter['availableAsOut'] = 1;
        }

        return $this->query(null, $filter);
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[Transition]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $entries = array();
        $params = array();

        $sql = '
        SELECT transitionId,
              transition,
              `code`,
              hasDuration,
              hasDirection,
              availableAsIn,
              availableAsOut
          FROM `transition`
         WHERE 1 = 1
        ';

        if ($this->getSanitizer()->getInt('transitionId', $filterBy) !== null) {
            $sql .= ' AND transition.transitionId = :transitionId ';
            $params['transitionId'] = $this->getSanitizer()->getInt('transitionId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('availableAsIn', $filterBy) !== null) {
            $sql .= ' AND transition.availableAsIn = :availableAsIn ';
            $params['availableAsIn'] = $this->getSanitizer()->getInt('availableAsIn', $filterBy);
        }

        if ($this->getSanitizer()->getInt('availableAsOut', $filterBy) !== null) {
            $sql .= ' AND transition.availableAsOut = :availableAsOut ';
            $params['availableAsOut'] = $this->getSanitizer()->getInt('availableAsOut', $filterBy);
        }

        if ($this->getSanitizer()->getString('code', $filterBy) != null) {
            $sql .= ' AND transition.code = :code ';
            $params['code'] = $this->getSanitizer()->getString('code', $filterBy);
        }

        // Sorting?
        if (is_array($sortOrder))
            $sql .= 'ORDER BY ' . implode(',', $sortOrder);



        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row);
        }

        return $entries;
    }
}