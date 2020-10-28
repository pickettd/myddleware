<?php

/*
  Testing
*/
namespace Myddleware\RegleBundle\Solutions;
use Symfony\Bridge\Monolog\Logger;

const currentLogString = " ($*^_^*$)";

class moodle extends moodlecore {
		// Permet de créer des données
		public function create($param) {
			$this->logger->error("info! we're in the start of moodle CREATE function".currentLogString);
			// Transformation du tableau d'entrée pour être compatible webservice Sugar
			foreach($param['data'] as $idDoc => $data) {
				$this->logger->error("--START each idDoc goes to data--");
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
				$this->logger->error("--END each idDoc goes to data");
			}
			$this->logger->error("info! we're in the end of moodle CREATE function".currentLogString);
			return $result;
		}

		// Permet de mettre à jour un enregistrement
	public function update($param) {
		$this->logger->error("info! we're in the start of moodle UPDATE function".currentLogString);
		// Transformation du tableau d'entrée pour être compatible webservice Sugar
		foreach($param['data'] as $idDoc => $data) {
			$this->logger->error("--START each idDoc goes to data--");
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

					}
					if (!empty($value)) {
						$obj->$key = $value;
					}
				}

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
				$this->logger->error("info! we're in the moodle UPDATE function - about to do moodleClient post");
				$response = $this->moodleClient->post($serverurl, $params);
				$this->logger->error("info! we're in the moodle UPDATE function - about to parse result of moodleClient post");
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
			$this->logger->error("--END each idDoc goes to data--");
		}
		$this->logger->error("info! we're in the end of moodle UPDATE function".currentLogString);
		return $result;
	}

  public function read($param) {
    $this->logger->error("info! we're in the start of moodle READ function".currentLogString);
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
				$this->logger->error("info! we're in the read function, FINISHED break down response by fields".currentLogString);
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
