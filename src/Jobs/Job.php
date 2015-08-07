<?php

  namespace ActiveCollab\JobsQueue\Jobs;

  use InvalidArgumentException;

  /**
   * @package ActiveCollab\JobsQueue\Jobs
   */
  abstract class Job
  {
    /**
     * @var array
     */
    private $data;

    /**
     * Construct a new Job instance
     *
     * @param array|null $data
     */
    public function __construct(array $data = null)
    {
      if (empty($data)) {
        $this->data = [];
      } else if (is_array($data)) {
        $this->data = $data;
      } else {
        throw new InvalidArgumentException('Data is expected to be an array or NULL');
      }
    }

    /**
     * @return mixed
     */
    abstract public function run();

    /**
     * @return array
     */
    protected function getData()
    {
      return $this->data;
    }
  }