<?php

  namespace ActiveCollab\JobsQueue\Jobs;

  use InvalidArgumentException;

  /**
   * @package ActiveCollab\JobsQueue\Jobs
   */
  abstract class Job
  {
    const NOT_A_PRIORITY = 0;
    const HAS_PRIORITY = 256;
    const HAS_HIGHEST_PRIORITY = 4294967295; // UNSIGNED INT https://dev.mysql.com/doc/refman/5.0/en/integer-types.html

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

      if (empty($this->data['priority']) || $this->data['priority'] < self::NOT_A_PRIORITY) {
        $this->data['priority'] = self::NOT_A_PRIORITY;
      } else if ($this->data['priority'] > self::HAS_HIGHEST_PRIORITY) {
        $this->data['priority'] = self::HAS_HIGHEST_PRIORITY;
      }
    }

    /**
     * @return mixed
     */
    abstract public function run();

    /**
     * @return array
     */
    public function getData()
    {
      return $this->data;
    }
  }