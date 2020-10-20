<?php

/*
  Testing
*/
namespace Myddleware\RegleBundle\Solutions;
use Symfony\Bridge\Monolog\Logger;

class moodle extends moodlecore {
  public function read($param) {
		try {
      $result['count'] = 0;

			// Put date ref in Moodle format
			$result['date_ref'] = $this->dateTimeFromMyddleware($param['date_ref']);
      $dateRefField = $this->getDateRefName($param['module'], $param['rule']['mode']);

			// Add requiered fields
			$param['fields'] = $this->addRequiredField($param['fields']);

			// Set parameters to call Moodle
			$parameters = $this->setParameters($param);

			// Get function to call Moodle
      $functionName = $this->getFunctionName($param);

			// Call to Moodle
			$serverurl = $this->paramConnexion['url'].'/webservice/rest/server.php'. '?wstoken=' .$this->paramConnexion['token']. '&wsfunction='.$functionName;
			$response = $this->moodleClient->post($serverurl, $parameters);
			$xml = $this->formatResponse('read', $response, $param);

			if (!empty($xml->ERRORCODE)) {
				throw new \Exception("Error $xml->ERRORCODE : $xml->MESSAGE");
			}

			// Transform the data to Myddleware format
			if (!empty($xml->MULTIPLE->SINGLE)) {
				foreach ($xml->MULTIPLE->SINGLE AS $data) {
					foreach ($data AS $field) {

						// Save the new date ref
						if (
								(
									$field->attributes()->__toString() == $dateRefField
								AND	$result['date_ref'] < $field->VALUE->__toString()
								)
							 OR (
									$field->attributes()->__toString() == 'date_ref_override' // The webservice could return a date to override the date_ref
								AND $field->VALUE->__toString() > 0
								)
						) {
							$result['date_ref'] = $field->VALUE->__toString();
						}
						// Get the date modified
						if (
								$field->attributes()->__toString() == $dateRefField
						) {
							$row['date_modified'] = $this->dateTimeToMyddleware($field->VALUE->__toString());
						}
						// Get all the requested fields
						if (array_search($field->attributes()->__toString(), $param['fields']) !== false) {
							$row[$field->attributes()->__toString()] = $field->VALUE->__toString();
						}
					}
					$result['values'][$row['id']] = $row;
					$result['count']++;
				}
			}
			// Put date ref in Myddleware format
			$result['date_ref'] = $this->dateTimeToMyddleware($result['date_ref']);
		}
		catch (\Exception $e) {
		    $result['error'] = 'Error : '.$e->getMessage().' '.$e->getFile().' Line : ( '.$e->getLine().' )';;
		}
		return $result;
	}

}
