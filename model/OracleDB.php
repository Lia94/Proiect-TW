<?php
require_once('User.php');
require_once('../config/paths.php');

$baza = new Database;
var_dump($baza->getUser(1));


//var_dump($baza->createUser("dsfssdsasdadf", "lordfsdfs.com", "dasdassd", "dfsdasddfsf"));

class Database
{
    private $connection = NULL;

    public function __construct()
    {
        $this->db_connect();
    }

    private function db_connect()
    {
        $this->connection = oci_connect('ArchiveR', 'anisia', 'localhost/xe');
        if (!$this->connection) {

            $e = oci_error();
            trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
            $this->connection - null;
        }


        $s = oci_parse($this->connection, 'select "u_lastname" from users');
        oci_execute($s, OCI_NO_AUTO_COMMIT);
        $row = oci_fetch_array($s, OCI_ASSOC);
        if (!$row) {
            echo "No rows\n";
        } else {
            do {
                foreach ($row as $item)
                    echo $item . " ";
                echo "\n";
            } while (($row = oci_fetch_array($s, OCI_ASSOC)) != false);
        }


    }


    function __destruct()
    {
        $this->disconnect();
    }

    function disconnect()
    {
        if ($this->connection != NULL) {
            oci_close($this->connection);
            $this->connection = NULL;
        }
    }

    function getConnection()
    {
        return $this->connection;
    }

    function isAdmin($userID)
    {
        if ($statement = oci_parse($this->connection, 'begin :nr := MAINPACKAGE.db_isAdmin(:usr); end;')) {
            if (oci_bind_by_name($statement, ":usr", $userID) && oci_bind_by_name($statement, ":nr", $nr)) {
                if (oci_execute($statement)) {
                    if ($nr != 0) {
                        return TRUE;
                    }
                }
            }
        }
        return FALSE;
    }

    function checkCredentials($username, $password)
    {
        $password = md5($password);
        if ($statement = oci_parse($this->connection, 'SELECT * FROM users WHERE ("u_username"= :username OR "u_email"=:email) AND "u_password"=:password')) {
            if (oci_bind_by_name($statement, ":username", $username) && oci_bind_by_name($statement, ":password", $password) && oci_bind_by_name($statement, ":email", $username)) {
                if (oci_execute($statement)) {
                    $result = oci_fetch_object($statement);
                    if ($result != false) {
                        return $result;
                    }
                }
            }
            return FALSE;
        }
    }

    function checkEmailAvailability($email, $userID = false)
    {

        $nr = null;
        if ($userID == false) {
            if ($statement = oci_parse($this->connection, "begin :nr := MAINPACKAGE.db_checkEmailAvailability(:email); end;")) {
                if (oci_bind_by_name($statement, ":email", $email) && oci_bind_by_name($statement, ":nr", $nr)) {
                    if (oci_execute($statement)) {
                        if ($nr == 0) {
                            return TRUE;
                        }
                    }
                }
            }
        } else {
            if ($statement = oci_parse($this->connection, "begin :nr := MAINPACKAGE.db_checkEmailAvailabilityuser(:email,:usr); end;")) {
                if (oci_bind_by_name($statement, ":email", $email)&& oci_bind_by_name($statement, ":usr", $userID)&& oci_bind_by_name($statement, ":nr", $nr)) {
                    if (oci_execute($statement)) {
                        if ($nr == 0) {
                            return TRUE;
                        }
                    }
                }
            }
        }
        return FALSE;
    }

    function createUser($firstname, $lastname, $email, $password)
    {
        $password = md5($password);
        if ($statement = oci_parse($this->connection, 'INSERT INTO users ("u_username", "u_password", "u_firstname", "u_lastname", "u_email", "u_picture") VALUES (:username,:password,:firstname,:lastname,:email,:picture)')) {
            $username = $this->generateUsername($firstname, $lastname);
            $picture = DEFAULT_PICTURE;
            if (oci_bind_by_name($statement, ":email", $email)
                && oci_bind_by_name($statement, ":username", $username)
                && oci_bind_by_name($statement, ":password", $password)
                && oci_bind_by_name($statement, ":firstname", $firstname)
                && oci_bind_by_name($statement, ":lastname", $lastname)
                && oci_bind_by_name($statement, ":picture", $picture)
            ) {
                if (oci_execute($statement)) {
                    if (oci_num_rows($statement) > 0) {
                        if ($statement2 = oci_parse($this->connection, 'SELECT * FROM users WHERE ("u_username"= :username OR "u_email"=:email)')) {
                            if (oci_bind_by_name($statement2, ":username", $username) && oci_bind_by_name($statement2, ":email", $username)) {
                                if (oci_execute($statement2)) {
                                    oci_commit($statement);
                                    $result = oci_fetch_object($statement2);
                                    $newUser = new User();
                                    $newUser->ID = $result->u_ID;
                                    $newUser->username = $username;
                                    return $newUser;
                                }
                            }
                        }

                    }
                }
            }
        }
        return false;
    }

    function generateUsername($firstname, $lastname)
    {
        $i = 0;
        if ($this->checkUsernameAvailability($firstname . '.' . $lastname)) {
            return $firstname . '.' . $lastname;
        }
        while (!$this->checkUsernameAvailability($firstname . '.' . $lastname . $i)) {
            $i++;
        }

        return $firstname . '.' . $lastname . $i;
    }

    function checkUsernameAvailability($username)
    {
        if ($statement = oci_parse($this->connection, "begin :nr := MAINPACKAGE.db_checkUsernameAvailability(:username); end;")) {
            if (oci_bind_by_name($statement, ":username", $username) && oci_bind_by_name($statement, ":nr", $nr)) {
                if (oci_execute($statement)) {
                    if ($nr == 0) {
                        return TRUE;
                    }

                }

            }
        }
        return FALSE;
    }

    function createModule($moduleName, $moduleHandler, $moduleLogo)
    {
        if (
        $statement = oci_parse($this->connection,
            "begin  MAINPACKAGE.db_createModule(:modulename, :modulehandler, :modulelogo); end;")
        ) {
            if (oci_bind_by_name($statement, ":modulename", $moduleName)
                && oci_bind_by_name($statement, ":modulehandler", $moduleHandler)
                && oci_bind_by_name($statement, ":modulelogo", $moduleLogo)
            ) {
                if (oci_execute($statement)) {
                    return TRUE;
                }
            }
        }
        return FALSE;
    }

    function saveProfile($userID, $firstname, $lastname, $email, $password = false)
    {
        if ($password != false) {
            if ($statement = oci_parse($this->connection, 'update users set "u_password"=:pass, "u_firstname"=:first, "u_lastname"=:last, "u_email"=:mail where "u_ID"=:uid')) {
                if ((oci_bind_by_name($statement, ":pass", $password))
                    && (oci_bind_by_name($statement, ":first", $firstname))
                    && (oci_bind_by_name($statement, ":last", $lastname))
                    && (oci_bind_by_name($statement, ":mail", $email))
                    && (oci_bind_by_name($statement, ":uid", $userID))
                ) {
                    if (oci_execute($statement)) {
                        return $this->getUser($userID);
                    }
                }
            }
        } else {
            if ($statement = oci_parse($this->connection, 'UPDATE users set "u_password"=:pass, "u_firstname"=:first, "u_lastname"=:last, "u_email"=:mail where "u_ID"=:uid')) {
                if ((oci_bind_by_name($statement, ":first", $firstname))
                    && (oci_bind_by_name($statement, ":last", $lastname))
                    && (oci_bind_by_name($statement, ":mail", $email))
                    && (oci_bind_by_name($statement, ":uid", $userID))
                ) {
                    if (oci_execute($statement)) {
                        return $this->getUser($userID);
                    }
                }

            }
        }
        return false;
    }

    function getUser($userID)
    {
        if ($statement = oci_parse($this->connection, 'SELECT * FROM users WHERE "u_userID"=:user')) {
            if (oci_bind_by_name($statement, ":user", $userID)) {
                if (oci_execute($statement)) {
                    $result = oci_fetch_object($statement);
                    if ($result != false) {
                        return $result;
                    }
                }
            }
        }
        return FALSE;
    }

    function createArchive($userID, $archiveName)
    {



        if ($statement = oci_parse($this->connection,'INSERT INTO archives (a_serverURL, a_size, a_password, a_creatorID, a_name) VALUES (:a_serverurl,:a_size,:a_password,:a_creatorid,:a_name)')) {
            $serverURL = $archiveName;
            $size = 0;
            $password = '';
            if (oci_bind_by_name($statement, ":a_serverurl", $serverURL)
            && oci_bind_by_name($statement, ":a_size", $size)
            && oci_bind_by_name($statement, ":a_password", $password)
            && oci_bind_by_name($statement, ":a_name", $archiveName))
            {
                if (oci_execute($statement)) {
                    if ($statement->insert_id > 0) {
                        return $statement->insert_id;
                    }
                }
            }
        }
        return false;
    }

    function deleteArchive($archiveID)
    {
        if ($statement = oci_parse($this->connection, "begin  MAINPACKAGE.db_deleteArchive(:archiveid); end;")) {
            if (oci_bind_by_name($statement, ":archiveid", $archiveID)) {
                if (oci_execute($statement)) {
                    return true;
                }
            }
        }
        return false;
    }

    function renameArchive($archiveID, $givenArchiveName)
    {
        if ($statement = oci_parse($this->connection, "begin  MAINPACKAGE.db_renameArchive(:archiveid,:given); end;")) {
            if (oci_bind_by_name($statement, ":archiveid", $archiveID)
                && oci_bind_by_name($statement, ":given", $givenArchiveName)
            ) {
                if (oci_execute($statement)) {
                    return true;
                }
            }
        }
        return false;
    }

    function retypeArchive($archiveID, $archiveURL)
    {
        if ($statement = oci_parse($this->connection, "begin  MAINPACKAGE.db_retypeArchive(:archiveid,:archiveurl); end;")) {
            if (oci_bind_by_name($statement, ":archiveid", $archiveID)
                && oci_bind_by_name($statement, ":archiveurl", $archiveURL)
            ) {
                if (oci_execute($statement)) {
                    return true;
                }
            }
        }
        return false;
    }

    function updateSizeOfArchive($archiveID, $archiveSize)
    {
        if ($statement = oci_parse($this->connection, "begin  MAINPACKAGE.db_updateSizeOfArchive(:archiveid,:archivesize); end;")) {
            if (oci_bind_by_name($statement, ":archiveid", $archiveID)
                && oci_bind_by_name($statement, ":archivesize", $archiveSize)
            ) {
                if (oci_execute($statement)) {
                    return true;
                }
            }
        }
        return false;
    }

    function getArchives($userID, $from, $nr)
    {

        if ($statement = oci_parse($this->connection, 'SELECT * FROM archives WHERE "a_creatorID"=:creatorid ORDER BY "a_creationdate" DESC LIMIT :from,:nr')) {
            if (oci_bind_by_name($statement, ":a_creatorID", $userID)
                && oci_bind_by_name($statement, ":from", $from)
                && oci_bind_by_name($statement, ":nr", $nr)
            ) {
                if (oci_execute($statement)) {
                    $result = $statement->get_result();
                    return $result;
                }
            }
        }
        return null;
    }

    function getNrArchives($userID)
    {
        if ($statement = oci_parse($this->connection, "begin :nr := MAINPACKAGE.db_getNrArchives(:userid); end;")) {
            if (oci_bind_by_name($statement, ":userid", $userID) && oci_bind_by_name($statement, ":nr", $nr)) {
                if (oci_execute($statement)) {
                    return $nr;
                }
            }
        }
        return null;
    }

    function getArchivesAfter($afterDate)
    {
        if ($statement = oci_parse($this->connection, 'SELECT "a_ID", "a_serverURL", "a_creationdate", "a_name", "a_size", "a_creationdate", "u_username", COUNT("f_ID") as nr_files
													FROM archives, users, files 
													WHERE "a_creationdate"<to_date(:afterdate,\'YYYY-MM-DD\') AND "a_creatorID"="u_ID" AND "f_archiveID"="a_ID"
													GROUP BY "a_ID", "a_serverURL", "a_creationdate", "a_name", "a_size", "a_creationdate", "u_username"')
        ) {
            if (oci_bind_by_name($statement, ":afterdate", $afterDate)) {
                if (oci_execute($statement)) {
                    $nrows = oci_fetch_all($statement, $result, null, null, OCI_FETCHSTATEMENT_BY_ROW);
                    return $result;
                }
            }
        }
        return null;
    }

    function getArchive($userID, $archiveID)
    {
        if ($statement = oci_parse($this->connection, 'SELECT * FROM archives WHERE "a_ID"=:aid AND "a_creatorID"=:creator')) {
            if (oci_bind_by_name($statement, ":aid", $archiveID) && oci_bind_by_name($statement, ":creator", $userID)) {
                if (oci_execute($statement)) {
                    $result = oci_fetch_object($statement);
                    if ($result != false) {
                        return $result;
                    }
                }
            }
        }
        return null;
    }

    function getArchiveTypes()
    {
        if ($statement = oci_parse($this->connection, 'SELECT * FROM archivetypes')) {
            if (oci_execute($statement)) {
                $nrows = oci_fetch_all($statement, $result, null, null, OCI_FETCHSTATEMENT_BY_ROW);
                if ($nrows > 0) {
                    return $result;
                }
            }
        }
        return null;
    }

    function getArchiveHandler($archiveTypeID)
    {
        if ($statement = oci_parse($this->connection, 'SELECT * FROM archivetypes WHERE "t_ID"=:tid')) {
            if (oci_bind_by_name($statement, ":tid", $archiveTypeID)) {
                if (oci_execute($statement)) {
                    $result = oci_fetch_object($statement);
                    if ($result != false) {
                        return $result;
                    }
                }
            }
        }
        return null;
    }

    function getArchiveHandlerByName($archiveTypeName)
    {
        if ($statement = oci_parse($this->connection, 'SELECT * FROM archivetypes WHERE "t_name"=UPPER(:tname)')) {
            if (oci_bind_by_name($statement, ":tname", $archiveTypeName)) {
                if (oci_execute($statement)) {
                    $result = oci_fetch_object($statement);
                    if ($result != false) {
                        return $result;
                    }
                }
            }
            return null;
        }
    }

    function createFile($archiveID, $fileName, $fileType, $fileSize)
    {
        if ($statement = oci_parse($this->connection, 'INSERT INTO files ("f_name", "f_archiveID", "f_type", "f_size") VALUES (:f_name,:f_aid,:f_type,:f_size)')) {
            if (oci_bind_by_name($statement, ":f_name", $fileName)
                && oci_bind_by_name($statement, ":f_aid", $archiveID)
                && oci_bind_by_name($statement, ":f_size", $fileSize)
                && oci_bind_by_name($statement, ":f_type", $fileType)
            ) {
                if (oci_execute($statement)) {
                    if (oci_num_rows($statement) > 0) {
                        if ($statement2 = oci_parse($this->connection, 'select "f_ID" from files where "f_name"=:f_name and "f_archiveID"=:f_archiveID')) {
                            if (oci_bind_by_name($statement2, ":f_archiveID", $archiveID)
                                && oci_bind_by_name($statement2, ":f_name", $fileName)
                            ) {
                                if (oci_execute($statement2)) {
                                    $result = oci_fetch_object($statement2);
                                    $id = $result->f_ID;
                                    return $id;
                                }
                            }
                        }

                    }
                }
            }
        }
        return false;
    }

    function markAsArchived($fileID)
    {
        if ($statement = oci_parse($this->connection, "begin  MAINPACKAGE.db_markAsArchived(:fileid); end;")) {
            if (oci_bind_by_name($statement, ":fileid", $fileID)) {
                if (oci_execute($statement)) {
                    return true;
                }
            }
        }
        return false;
    }

    function deleteFile($fileID)
    {
        if ($statement = oci_parse($this->connection, "begin  MAINPACKAGE.db_deleteFile(:fileid); end;")) {
            if (oci_bind_by_name($statement, ":fileid", $fileID)) {
                if (oci_execute($statement)) {
                    return true;
                }
            }
        }
        return false;
    }

    function getFile($userID, $fileID)
    {
        if ($statement = oci_parse($this->connection, 'SELECT "f_ID", "f_name", "f_size", "f_type", "f_adddate", "f_archived", "a_serverURL" FROM files, archives WHERE "f_ID" =:file AND "a_ID" = "f_archiveID" AND "a_creatorID" =:user')) {
            if (oci_bind_by_name($statement, ":file", $fileID)
                && oci_bind_by_name($statement, ":user", $userID)
            ) {
                if (oci_execute($statement)) {
                    $result = oci_fetch_object($statement);
                    if ($result != false) {
                        return $result;
                    }
                }
            }
        }
        return null;
    }

    function getFiles($userID, $archiveID)
    {
        if ($statement = oci_parse($this->connection, 'SELECT "f_ID", "f_name", "f_size", "f_type", "f_adddate", "f_archived", "a_serverURL" FROM files, archives WHERE "f_archiveID" =:archiv AND "a_ID" = "f_archiveID" AND "a_creatorID" =:creator')) {
            if (oci_bind_by_name($statement, ":archiv", $archiveID) && oci_bind_by_name($statement, ":creator", $userID)) {
                if (oci_execute($statement)) {
                    $nrows = oci_fetch_all($statement, $result, null, null, OCI_FETCHSTATEMENT_BY_ROW);
                    return $result;
                }
            }
        }
        return null;
    }

    function getSettings()
    {
        $result = array();
        if ($statement = oci_parse($this->connection, "SELECT * FROM settings")) {
            if (oci_execute($statement)) {
                $nrows = oci_fetch_all($statement, $result, null, null, OCI_FETCHSTATEMENT_BY_ROW);
                if ($nrows > 0) {
                    $settings = array();
                    foreach ($result as $row) {
                        $settings[$row['s_name']] = $row['s_value'];
                    }
                    return $settings;
                }
            }
        }
        return null;
    }

    function saveSettings($defaultName, $maximumArchiveSize, $maximumFileSize)
    {
        if ($statement = oci_parse($this->connection, "begin  MAINPACKAGE.db_saveSettings(:defaultname,:maximuma,:maximumfile); end;")) {
            if (oci_bind_by_name($statement, ":defaultname", $defaultName)
                && oci_bind_by_name($statement, ":maximuma", $maximumArchiveSize)
                && oci_bind_by_name($statement, ":maximumfile", $maximumFileSize)
            ) {
                if (oci_execute($statement)) {
                    return true;
                }
            }
        }

        return false;
    }

    function executeQuery($query)
    {
        return $this->connection->query($query);
    }
}

?>
