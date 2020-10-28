<?php

/*
  Testing
*/
namespace Myddleware\RegleBundle\Solutions;
use Symfony\Bridge\Monolog\Logger;

class moodle extends moodlecore {
  public function read($param) {
    $this->logger->error("info! we're in the start of moodle read function");
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
				$this->logger->error("info! we're in the read function, about to break down response by fields (^___^)");
				//$this->logger->error("this is the data that has fields inside: ".print_r($param['fields'],true));
				foreach ($xml->MULTIPLE->SINGLE AS $data) {
					$this->logger->error('---Start of a user---');
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
							// This records all attributes that match our fields list
							//$this->logger->error('ATTRIB: '.print_r($field->attributes()->__toString(),true));
							if ($field->attributes()->__toString() == "customfields") {
								//$this->logger->error('WHOLE VALUE: '.print_r($field,true));
									if (!empty($field->MULTIPLE->SINGLE)) {
										$this->logger->error('This is customfield and the field->multiple is not empty');
										foreach($field->MULTIPLE->SINGLE as $customfield) {
											foreach($customfield->KEY as $property) {
												if ($property->attributes()->__toString() == 'shortname') {
													$this->logger->error('SHORTNAME: '.print_r($property->VALUE->__toString(), true));
												}
												if ($property->attributes()->__toString() == 'value') {
													$this->logger->error('VALUE: '.print_r($property->VALUE->__toString(), true));
												}
											}
										}
									}
								}
							/*else {
								// This records the value of all non-customfields in our field list
								$this->logger->error('VALUE: '.print_r($field->VALUE->__toString(),true));
							}*/
							//$this->logger->error('---End of this field---');
							$row[$field->attributes()->__toString()] = $field->VALUE->__toString();
						}
					}
					$this->logger->error('---End of a user---');
					$result['values'][$row['id']] = $row;
					$result['count']++;
				}
				$this->logger->error("info! we're in the read function, FINISHED break down response by fields (^___^)");
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
