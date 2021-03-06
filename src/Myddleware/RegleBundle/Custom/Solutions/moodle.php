<?php

/*
  Testing
*/
namespace Myddleware\RegleBundle\Solutions;
use Symfony\Bridge\Monolog\Logger;

// Note that currentLogString is used by the debugging log output lines to more easily tell from the log which version of the code was run
//const currentLogString = " ^-----^";

class moodle extends moodlecore {
		// Permet de créer des données
		public function create($param) {
			// Note that $this->logger->error will show up in the server log (either dev.log or prod.log)
			// The logger statements in this file that are commented out are for testing/debugging this custom code
			//$this->logger->error("info! we're in the start of moodle CREATE function".currentLogString);
			// Transformation du tableau d'entrée pour être compatible webservice Sugar
			foreach($param['data'] as $idDoc => $data) {
				//$this->logger->error("--START each idDoc goes to data--");
				try {
					// Check control before create
					$data = $this->checkDataBeforeCreate($param, $data);
					$dataSugar = array();
					$obj = new \stdClass();
					foreach ($data as $key => $value) {
						// We don't send Myddleware_element_id field to Moodle
						if ($key == 'Myddleware_element_id') {
							continue;
						}
						elseif ($key == 'customfields') {
							// I think we will try to not write anything specifically to the customfield itself?
							// the code below did work for writing in what we wanted from Salesforce into the customfield part
							/*$this->logger->error("found key for customfields, going to try to make an array that has a child1_email key and value of this value");
							if (!empty($value)) {
								$this->logger->error("found key for customfields, and value is not empty");
								$obj->$key = array(array('type'=> 'child1_email', 'value' => $value));
								$this->logger->error("found key for customfields, maybe inserted an assoc. array that has a child1_email key and value of this value?");
							}*/
							continue;
						}
						elseif ($key == 'child1_email') {
							if (!empty($value)) {
								if (!empty($obj->customfields) ) {
									$obj->customfields[] = array('type'=> 'child1_email', 'value' => $value);
								}
								else {
									$obj->customfields = array(array('type'=> 'child1_email', 'value' => $value));
								}
							}
							continue;
						}
						elseif ($key == 'child2_email') {
							if (!empty($value)) {
								if (!empty($obj->customfields) ) {
									$obj->customfields[] = array('type'=> 'child2_email', 'value' => $value);
								}
								else {
									$obj->customfields = array(array('type'=> 'child2_email', 'value' => $value));
								}
							}
							continue;
						}
						elseif ($key == 'parent1_email') {
							if (!empty($value)) {
								if (!empty($obj->customfields) ) {
									$obj->customfields[] = array('type'=> 'parent1_email', 'value' => $value);
								}
								else {
									$obj->customfields = array(array('type'=> 'parent1_email', 'value' => $value));
								}
							}
							continue;
						}
						elseif ($key == 'parent2_email') {
							if (!empty($value)) {
								if (!empty($obj->customfields) ) {
									$obj->customfields[] = array('type'=> 'parent2_email', 'value' => $value);
								}
								else {
									$obj->customfields = array(array('type'=> 'parent2_email', 'value' => $value));
								}
							}
							continue;
						}
						if (!empty($value)) {
							$obj->$key = $value;
						}
					}
					switch ($param['module']) {
						case 'users':
							$users = array($obj);
							$params = array('users' => $users);
							$functionname = 'core_user_create_users';
							break;
						case 'courses':
							$courses = array($obj);
							$params = array('courses' => $courses);
							$functionname = 'core_course_create_courses';
							break;
						case 'groups':
							$groups = array($obj);
							$params = array('groups' => $groups);
							$functionname = 'core_group_create_groups';
							break;
						case 'group_members':
							$members = array($obj);
							$params = array('members' => $members);
							$functionname = 'core_group_add_group_members';
							break;
						case 'manual_enrol_users':
							$enrolments = array($obj);
							$params = array('enrolments' => $enrolments);
							$functionname = 'enrol_manual_enrol_users';
							break;
						case 'manual_unenrol_users':
							break;
						case 'notes':
							$notes = array($obj);
							$params = array('notes' => $notes);
							$functionname = 'core_notes_create_notes';
							break;
						default:
							throw new \Exception("Module unknown. ");
							break;
					}

					$serverurl = $this->paramConnexion['url'].'/webservice/rest/server.php'. '?wstoken=' .$this->paramConnexion['token']. '&wsfunction='.$functionname;
					$response = $this->moodleClient->post($serverurl, $params);
					$xml = simplexml_load_string($response);

					// Réponse standard pour les modules avec retours
					if (
							!empty($xml->MULTIPLE->SINGLE->KEY->VALUE)
						&& !in_array($param['module'],array('manual_enrol_users','group_members'))
					) {
						$result[$idDoc] = array(
								'id' => $xml->MULTIPLE->SINGLE->KEY->VALUE,
								'error' => false
						);
					}
					elseif (
							!empty($xml->MULTIPLE->SINGLE->KEY[1]->VALUE)
						&& in_array($param['module'],array('notes'))
					) {
						$result[$idDoc] = array(
								'id' => $xml->MULTIPLE->SINGLE->KEY[1]->VALUE,
								'error' => false
						);
					}
					elseif (!empty($xml->ERRORCODE)) {
						throw new \Exception($xml->ERRORCODE.' : '.$xml->MESSAGE);
					}
					// Si pas d'erreur et module sans retour alors on génère l'id
					elseif(in_array($param['module'],array('manual_enrol_users'))) {
						$result[$idDoc] = array(
								'id' => $obj->courseid.'_'.$obj->userid.'_'.$obj->roleid,
								'error' => false
						);
					}
					elseif(in_array($param['module'],array('group_members'))) {
						$result[$idDoc] = array(
								'id' => $obj->groupid.'_'.$obj->userid,
								'error' => false
						);
					}
					else {
						throw new \Exception('Error unknown. ');
					}
				}
				catch (\Exception $e) {
					$error = $e->getMessage();
					$result[$idDoc] = array(
							'id' => '-1',
							'error' => $error
					);
				}
				// Modification du statut du flux
				$this->updateDocumentStatus($idDoc,$result[$idDoc],$param);
				//$this->logger->error("--END each idDoc goes to data");
			}
			//$this->logger->error("info! we're in the end of moodle CREATE function".currentLogString);
			return $result;
		}

		// Permet de mettre à jour un enregistrement
	public function update($param) {
		// Note that $this->logger->error will show up in the server log (either dev.log or prod.log)
		// The logger statements in this file that are commented out are for testing/debugging this custom code
		//$this->logger->error("info! we're in the start of moodle UPDATE function".currentLogString);
		// Transformation du tableau d'entrée pour être compatible webservice Sugar
		foreach($param['data'] as $idDoc => $data) {
			//$this->logger->error("--START each idDoc goes to data--");
			try {
				// Check control before update
				$data = $this->checkDataBeforeUpdate($param, $data);
				$dataSugar = array();
				$obj = new \stdClass();
				foreach ($data as $key => $value) {
					if ($key == 'target_id') {
						continue;
					// We don't send Myddleware_element_id field to Moodle
					} elseif ($key == 'Myddleware_element_id') {
						continue;
					}
					elseif ($key == 'customfields') {
						// I think we will try to not write anything specifically to the customfield itself?
						// the code below did work for writing in what we wanted from Salesforce into the customfield part
						/*$this->logger->error("found key for customfields, going to try to make an array that has a child1_email key and value of this value");
						if (!empty($value)) {
							$this->logger->error("found key for customfields, and value is not empty");
							$obj->$key = array(array('type'=> 'child1_email', 'value' => $value));
							$this->logger->error("found key for customfields, maybe inserted an assoc. array that has a child1_email key and value of this value?");
						}*/
						continue;
					}
					elseif ($key == 'child1_email') {
						if (!empty($value)) {
							if (!empty($obj->customfields) ) {
								$obj->customfields[] = array('type'=> 'child1_email', 'value' => $value);
							}
							else {
								$obj->customfields = array(array('type'=> 'child1_email', 'value' => $value));
							}
						}
						continue;
					}
					elseif ($key == 'child2_email') {
						if (!empty($value)) {
							if (!empty($obj->customfields) ) {
								$obj->customfields[] = array('type'=> 'child2_email', 'value' => $value);
							}
							else {
								$obj->customfields = array(array('type'=> 'child2_email', 'value' => $value));
							}
						}
						continue;
					}
					elseif ($key == 'parent1_email') {
						if (!empty($value)) {
							if (!empty($obj->customfields) ) {
								$obj->customfields[] = array('type'=> 'parent1_email', 'value' => $value);
							}
							else {
								$obj->customfields = array(array('type'=> 'parent1_email', 'value' => $value));
							}
						}
						continue;
					}
					elseif ($key == 'parent2_email') {
						if (!empty($value)) {
							if (!empty($obj->customfields) ) {
								$obj->customfields[] = array('type'=> 'parent2_email', 'value' => $value);
							}
							else {
								$obj->customfields = array(array('type'=> 'parent2_email', 'value' => $value));
							}
						}
						continue;
					}
					if (!empty($value)) {
						$obj->$key = $value;
					}
				}
				//$this->logger->error("finished looking through all the fields to update, now myddleware will do a switch on the type of module");

				// Fonctions et paramètres différents en fonction des appels webservice
				switch ($param['module']) {
					case 'users':
						$obj->id = $data['target_id'];
						$users = array($obj);
						$params = array('users' => $users);
						$functionname = 'core_user_update_users';
						break;
					case 'courses':
						$obj->id = $data['target_id'];
						$courses = array($obj);
						$params = array('courses' => $courses);
						$functionname = 'core_course_update_courses';
						break;
					case 'manual_enrol_users':
						$enrolments = array($obj);
						$params = array('enrolments' => $enrolments);
						$functionname = 'enrol_manual_enrol_users';
						break;
					case 'notes':
						$obj->id = $data['target_id'];
						unset($obj->userid);
						unset($obj->courseid);
						$notes = array($obj);
						$params = array('notes' => $notes);
						$functionname = 'core_notes_update_notes';
						break;
					case 'group_members':
						$members = array($obj);
						$params = array('members' => $members);
						$functionname = 'core_group_add_group_members';
						break;
					default:
						throw new \Exception("Module unknown. ");
						break;
				}

				$serverurl = $this->paramConnexion['url'].'/webservice/rest/server.php'. '?wstoken=' .$this->paramConnexion['token']. '&wsfunction='.$functionname;
				//$this->logger->error("info! we're in the moodle UPDATE function - about to do moodleClient post");
				$response = $this->moodleClient->post($serverurl, $params);
				//$this->logger->error("info! we're in the moodle UPDATE function - about to parse result of moodleClient post");
				$xml = simplexml_load_string($response);

				// Réponse standard pour les modules avec retours
				if (!empty($xml->ERRORCODE)) {
					throw new \Exception($xml->ERRORCODE.' : '.$xml->MESSAGE.(!empty($xml->DEBUGINFO) ? ' Debug : '.$xml->DEBUGINFO : ''));
				}
				// Si pas d'erreur et module sans retour alors on génère l'id
				elseif(in_array($param['module'],array('manual_enrol_users'))) {
					$result[$idDoc] = array(
							'id' => $obj->courseid.'_'.$obj->userid.'_'.$obj->roleid,
							'error' => false
					);
				}
				elseif(in_array($param['module'],array('group_members'))) {
					$result[$idDoc] = array(
							'id' => $obj->groupid.'_'.$obj->userid,
							'error' => false
					);
				}
				else {
					$result[$idDoc] = array(
							'id' => $obj->id,
							'error' => false
					);
				}
			}
			catch (\Exception $e) {
				$error = $e->getMessage();
				$result[$idDoc] = array(
						'id' => '-1',
						'error' => $error
				);
			}
			// Modification du statut du flux
			$this->updateDocumentStatus($idDoc,$result[$idDoc],$param);
			//$this->logger->error("--END each idDoc goes to data--");
		}
		//$this->logger->error("info! we're in the end of moodle UPDATE function".currentLogString);
		return $result;
	}

  public function read($param) {
	// Note that $this->logger->error will show up in the server log (either dev.log or prod.log)
	// The logger statements in this file that are commented out are for testing/debugging this custom code
    //$this->logger->error("info! we're in the start of moodle READ function".currentLogString);
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
				// When we get a response from Moodle, we will have to parse the custom profile fields, make sure there is at least an empty string for requested properties
				$supportedMoodleProfileFields = array('customfields', 'child1_email', 'child2_email', 'parent1_email', 'parent2_email');
				foreach ($supportedMoodleProfileFields AS $supportedCustomField) {
					if (array_search($supportedCustomField, $param['fields']) !== false) {
						$row[$supportedCustomField] = "";
					}
				}
				//$this->logger->error("this is the data that has fields inside: ".print_r($param['fields'],true));
				foreach ($xml->MULTIPLE->SINGLE AS $data) {
					//$this->logger->error('---Start of a user---');
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
							if ($field->attributes()->__toString() == "customfields") {
								$row['customfields'] = "This user has custom Moodle profile fields";
								//$this->logger->error('WHOLE VALUE: '.print_r($field,true));
									if (!empty($field->MULTIPLE->SINGLE)) {
										//$this->logger->error('This is customfield and the field->multiple is not empty');
										foreach($field->MULTIPLE->SINGLE as $customfield) {
											// This logic searches the customfield for the shortName and value (and uses it if we're looking for it)
											$shortName = "";
											$customValue = "";
											foreach($customfield->KEY as $property) {
												if ($property->attributes()->__toString() == 'shortname') {
													$shortName = $property->VALUE->__toString();
												}
												else if ($property->attributes()->__toString() == 'value') {
													$customValue = $property->VALUE->__toString();
												}
											}
											if (array_search($shortName, $param['fields']) !== false) {
												//$this->logger->error('found the custom field shortName in the properties that we want');
												//$this->logger->error('SHORTNAME: '.print_r($shortName, true));
												//$this->logger->error('VALUE: '.print_r($customValue, true));
												$row[$shortName] = $customValue;
											}
										}
									}
								}
							else {
								// This is the default logic for non-custom-profile fields
								$row[$field->attributes()->__toString()] = $field->VALUE->__toString();
							}
						}
					}
					//$this->logger->error('---End of a user---');
					$result['values'][$row['id']] = $row;
					$result['count']++;
				}
				//$this->logger->error("info! we're in the read function, FINISHED break down response by fields".currentLogString);
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
