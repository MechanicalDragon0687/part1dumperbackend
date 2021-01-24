<?php

namespace BruteforceMovable\Controllers;

use BruteforceMovable\BaseController;
use BruteforceMovable\DatabaseManager;
use BruteforceMovable\Validation\FriendCode;
use BruteforceMovable\Validation\ID0;

class GetStatusController extends BaseController {
    protected $viewFolder = '';
    
    public function resettimeoutAction() {
        if (isset($this->request->get['fc'])) {
            $dbCon = DatabaseManager::getHandle();
            $statement = $dbCon->prepare('update seedqueue set claimedby = null where friendcode like ?');
            $results = $statement->execute([$this->request->get['fc']]);
        }
    }

    public function unlockAction() {
        if (isset($this->request->get['fc'])) {
            if (isset($this->request->get['id0'])) {
                $id0 = $this->request->get['id0'];
            }else{
                $id0 = '%'; // wildcard
            }
            $friendcode = $this->request->get['fc'];
            if (ID0::IsValid($id0) && FriendCode::IsValid($friendcode)) {
                $dbCon = DatabaseManager::getHandle();
                $statement = $dbCon->prepare('update seedqueue set claimedby = null, state = 0 where friendcode like ? AND id0 like ?');
                $results = $statement->execute([$friendcode,$id0]);
                return "success";
            }
        }
        return "error";
    }

	public function indexAction() {
        if (isset($this->request->get['id0']) && isset($this->request->get['friendcode'])) {
            $dbCon = DatabaseManager::getHandle();

            $statement = $dbCon->prepare('select *, TIMESTAMPDIFF(MINUTE, time_started, now()) > 10 as timeout from seedqueue where friendcode like ?');
            $results = $statement->execute([$this->request->get['friendcode']]);  
            if (count($results) == 0) {
                //we need to add a new entry

                $statement = $dbCon->prepare('insert into seedqueue (friendcode, id0) values (?, ?)');
                $statement->execute([$this->request->get['friendcode'], $this->request->get['id0']]);  

                return json_encode(array(
                    'lfcs' => null,
                    'dumped' => false,
                    'claimedBy' => null,
                    'timeout' => false,
                    'lockout' => false
                ));
            }

            return json_encode(array(
                'lfcs' => $results[0]['part1b64'],
                'dumped' => strlen($results[0]['part1b64']) > 0,
                'claimedBy' => $results[0]['claimedby'],
                'timeout' => $results[0]['timeout'] && intval($results[0]['claimedby']) > 0 ? true : false,
                'lockout' => $results[0]['timeout'] && $results[0]['state'] >= 3
            ));
        }
        return "error";
    }
}
