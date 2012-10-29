<?php
/**
 * @see https://github.com/ingoclaro/cake_batch
 */
class BatchBehavior extends ModelBehavior {
	protected $_defaults = array(
		'delimiter' => 'auto', //auto: auto detect delimiter, or provide the delimiter char.
		'header' => array(), //optional header array. If not provided use first line as header.
		'hash' => false, //should calculate hash of the lines? (in validation only). Good for skipping duplicate data files.
	);

	private $hash_handler = null;

	private $header = array();

	private $delimiter = ';';

	public function setup(&$Model, $config = array()) {

		if (!isset($this->settings[$Model->alias])) {
			$this->settings[$Model->alias] = $this->_defaults;
		}

		$this->settings[$Model->alias] = array_merge($this->settings[$Model->alias], $config);

		if ($this->settings[$Model->alias]['hash'] && !function_exists('hash_init')) {
			$this->settings[$Model->alias]['hash'] = false;
		}

		$this->header = $this->settings[$Model->alias]['header'];
		$this->delimiter = $this->settings[$Model->alias]['delimiter'];

		ini_set('auto_detect_line_endings', 1);
	}


	/**
	 * Validate a csv file with the model validation rules
	 *
	 * @param Model attached to this validation.
	 * @param String $file, path to the file to validate.
	 * @return Array validation result with optional hash result
	 */
	public function batchValidate(&$Model, $file) {
		$fp = @fopen($file, 'r');

		if (!$fp) {
			return array(
				'row' => false,
				'data' => $file,
				'errors' => array('file' => __('Could not open file', true)),
			);
		}

		$invalidFields = array();

		$first = true;

		//determine delimiter if auto.
		if ($this->settings[$Model->alias]['delimiter'] == 'auto') {
			$this->delimiter = $this->_detectDelimiter($fp);

			if ($this->delimiter === false) {
				return array(
					'row' => false,
					'data' => $file,
					'errors' => array('file' => __('Could not determine delimiter', true)),
				);
			}
		}

		if ($this->settings[$Model->alias]['hash']) {
			$this->hash_handler = hash_init('sha1');
		}

		$row = 0;

		while (($items = fgetcsv($fp, 0, $this->delimiter) ) !== false) {
			$row++;

			//process first row.
			if ($first) {
				$first = false;
				$skip = $this->_processFirstLine($items);
				if($skip) {
					continue;
				}
			}

			//check for line with blank values (,,,,, or ;;;;;)
			if($this->_isBlankLine($items)) {
				continue;
			}

			//the line must have the same fields as the header.
			if (count($this->header) != count($items)) {
				$invalidFields[] = array(
					'row' => $row,
					'data' => $items,
					'errors' => array('line' => __('Number of items doesn\'t match header.', true)),
				);
				continue;
			}

			$data = array_combine($this->header, $items);

			if ($this->settings[$Model->alias]['hash']) {
				$this->_updateHash($data);
			}

			//cake validation.
			$Model->create();

			if (!$Model->save($data, array('validate' => 'only'))) {
				$invalidFields[] = array(
					'row' => $row,
					'data' => $data,
					'errors' => $Model->validationErrors,
				);
			}
		} // while (($items = fgetcsv($fp, 0, $this->delimiter) ) !== false) {

		fclose($fp);

		if ($this->settings[$Model->alias]['hash']) {
			$invalidFields['hash'] = hash_final($this->hash_handler);
		}

		return $invalidFields;
	}


	/**
	 * Process a csv file line by line
	 *
	 * @param Model attached to this process
	 * @param String $file: path to the file to process
	 * @param String $callback: function name inside the model to call for each record
	 * @param var $extra: extra data to pass to the callback function.
	 */
	public function batchProcess($Model, $file, $callback, $extra = null) {
		$first = true;
		$row = 0;

		$result = array(
			'total' => 0,
			'ok' => 0,
			'skipped' => 0,
			'failed' => 0,
			'reasons' => array(),
			'rows' => array(
			'ok' => array(),
			'failed' => array(),
			),
		);


		$fp = @fopen($file, 'r');

		if (!$fp) {
			return array(
				'total' => -1,
				'ok' => 0,
				'skipped' => 0,
				'failed' => 1,
				'reasons' => array(__('Could not open file', true) => 1),
				'rows' => array(
					'ok' => array(),
					'failed' => array(
						'row' => -1,
						'error' => __('Could not open file', true),
					),
				),
			);
		}


		//auto detect delimiter
		if ($this->settings[$Model->alias]['delimiter'] == 'auto') {
			$this->delimiter = $this->_detectDelimiter($fp);
			if ($this->delimiter === false) {
				return array(
					'total' => -1,
					'ok' => 0,
					'skipped' => 0,
					'failed' => 1,
					'reasons' => array(__('Could not determine delimiter', true) => 1),
					'rows' => array(
						'ok' => array(),
						'failed' => array(
							'row' => -1,
							'error' => __('Could not determine delimiter', true),
						),
					),
				);
			}
		}

		while (($items = fgetcsv($fp, 0, $this->delimiter)) !== false) {

			$row++;
			$result['total']+= 1;

			//process first line
			if ($first) {
				$first = false;
				$skip = $this->_processFirstLine($items);
				if($skip) {
					$result['skipped']+= 1;
					continue;
				}
			}

			//check for line with blank values (,,,,, or ;;;;;)
			if($this->_isBlankLine($items)) {
				$result['skipped']+= 1;
				continue;
			}


			//line should have the same items as header.
			if ( count($this->header) != count($items) ) {
				$result['failed']+= 1;
				$result['rows']['failed'][] = array(
					'row' => $row,
					'error' => __('Number of items doesn\'t match header.', true),
				);
				$result['reasons'][__('Number of items doesn\'t match header.', true)] = (isset($result['reasons'][__('Number of items doesn\'t match header.', true)])? $result['reasons'][__('Number of items doesn\'t match header.', true)] + 1 : 1);
				continue;
			}

			$data = array_combine($this->header, $items);

			//do the callback call
			$ret = call_user_func_array(array($Model, $callback), array($data, $extra));

			if ($ret === true) {
				$result['ok']+= 1;
				$result['rows']['ok'][] = array(
					'row' => $row,
				);
			} else {
				$result['failed']+= 1;
				$result['rows']['failed'][] = array(
					'row' => $row,
					'error' => $ret
				);
				$result['reasons'][$ret] = (isset($result['reasons'][$ret])? $result['reasons'][$ret] + 1 : 1);
			}
		} // while (($items = fgetcsv($fp, 0, $delimiter)) !== false) {

		fclose($fp);

		//dispatch event
		if ($Model->Behaviors->attached('Event')) {
			$Model->dispatchEvent('afterBatchProcess', array(
				'result' => $result,
				'file' => $file,
				'extra' => $extra,
			));
		}

		return $result;
	}


	/**
	 * process first line of file
	 * @param items array of items
	 * @return boolean should skip line?
	 */
	private function _processFirstLine($items) {
		$skip = false;

		//if no header, use first line as header.
		if (empty($this->header)) {
			$this->header = $items;
			$skip = true;
		} else {
			//skip line if it equals header.
			$diff = array_diff($this->header, $items);
			if (empty($diff)) {
				$skip = true;
			}
		}

		return $skip;
	}


	/**
	 * update the computed hash of the file
	 *
	 */
	private function _updateHash($data) {
		ksort($data);
		$str = implode(';', $data);
		hash_update($this->hash_handler, $str);
	}

	/**
	 * checks if a line is blank
	 * ,,,,, or ;;;;;
	 * @param array items of the line
	 * @return boolean
	 */
	private function _isBlankLine($items) {
		$blank = false;
		$temp = array_fill(0, count($items), '');
		$diff = array_diff($items, $temp);

		if (empty($diff)) {
			$blank = true;
		}
		return $blank;
	}

	/**
	 * Try to automatically detect the delimiter of the csv file.
	 * @param $fp file pointer
	 * @return String delimiter [,;] or false
	 */
	private function _detectDelimiter($fp) {
		$line1 = fgetcsv($fp, 0, ',');
		rewind($fp);
		$line2 = fgetcsv($fp, 0, ';');
		rewind($fp);

		//check header
		if (!empty($this->header)) {
			$diff = array_diff($this->header, $line1);

			if (empty($diff)) {
				$delimiter = ',';
			}

			$diff = array_diff($this->header, $line2);

			if (empty($diff)) {
				$delimiter = ';';
			}
		} else {
			if (count($line1) == count($line2)) {
				//could not determine auto delimiter.
				return false;
			}
			if (count($line1) > count($line2)) {
				$delimiter = ',';
			} else {
				$delimiter = ';';
			}
		}

		return $delimiter;
	}
}
