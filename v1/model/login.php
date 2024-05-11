<?php
    class Login {
        private $_id;
        private $_studentNumber;
        private $_yearGraduated;
        private $_courseId;
        private $_firstName;
        private $_middleName;
        private $_lastName;
        private $_emailAddress;
        private $_isPasswordChanged;
        private $_course;

        public function __construct(
            $id,
            $studentNumber,
            $yearGraduated,
            $courseId,
            $firstName,
            $middleName,
            $lastName,
            $emailAddress,
            $isPasswordChanged,
            $course
        ) {
            $this->setId($id);
            $this->setStudentNumber($studentNumber);
            $this->setYearGraduated($yearGraduated);
            $this->setCourseId($courseId);
            $this->setFirstName($firstName);
            $this->setMiddleName($middleName);
            $this->setLastName($lastName);
            $this->setEmailAddress($emailAddress);
            $this->setIsPasswordChanged($isPasswordChanged);
            $this->setCourse($course);
        }

        /* Setters */
        public function setId($id) {
            $this->_id = $id;
        }

        public function setStudentNumber($studentNumber) {
            $this->_studentNumber = $studentNumber;
        }

        public function setYearGraduated($yearGraduated) {
            $this->_yearGraduated = $yearGraduated;
        }

        public function setCourseId($courseId) {
            $this->_courseId = $courseId;
        }

        public function setFirstName($firstName) {
            $this->_firstName = $firstName;
        }

        public function setMiddleName($middleName) {
            $this->_middleName = $middleName;
        }

        public function setLastName($lastName) {
            $this->_lastName = $lastName;
        }

        public function setEmailAddress($emailAddress) {
            $this->_emailAddress = $emailAddress;
        }

        public function setIsPasswordChanged($isPasswordChanged) {
            $this->_isPasswordChanged = $isPasswordChanged;
        }

        public function setCourse($course) {
            $this->_course = $course;
        }

        /* Getters */
        public function getId() {
            return $this->_id;
        }

        public function getStudentNumber() {
            return $this->_studentNumber;
        }

        public function getYearGraduated() {
            return $this->_yearGraduated;
        }

        public function getCourseId() {
            return $this->_courseId;
        }

        public function getFirstName() {
            return $this->_firstName;
        }

        public function getMiddleName() {
            return $this->_middleName;
        }

        public function getLastName() {
            return $this->_lastName;
        }

        public function getEmailAddress() {
            return $this->_emailAddress;
        }

        public function getIsPasswordChanged() {
            return $this->_isPasswordChanged;
        }

        public function getCourse() {
            return $this->_course;
        }

        public function returnLoginAsArray() {
            $login = array();
            $login["id"] = $this->getId();
            $login["studentNumber"] = $this->getStudentNumber();
            $login["yearGraduated"] = $this->getYearGraduated();
            $login["courseId"] = $this->getCourseId();
            $login["firstName"] = $this->getFirstName();
            $login["middleName"] = $this->getMiddleName();
            $login["lastName"] = $this->getLastName();
            $login["emailAddress"] = $this->getEmailAddress();
            $login["isPasswordChanged"] = $this->getIsPasswordChanged();
            $login["course"] = $this->getCourse();
            return $login;
        }
    }
?>
