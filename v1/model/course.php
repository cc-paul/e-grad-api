<?php
	class Courses {
		private $_id;
		private $_course;
		private $_description;

		public function __construct(
			$id,
			$course,
			$description
		) {
			$this->setId($id);
			$this->setCourse($course);
			$this->setDescription($description);
		}

		/* Setters */
		public function setId($id) {
			$this->_id = $id;
		}

		public function setCourse($course) {
			$this->_course = $course;
		}

		public function setDescription($description) {
			$this->_description = $description;
		}

		/* Getters */
		public function getId() {
			return $this->_id;
		}

		public function getCourse() {
			return $this->_course;
		}

		public function getDescription() {
			return $this->_description;
		}

		public function returnCourseAsArray() {
			$course = array();
			$course["id"]          = $this->getId();
			$course["course"]      = $this->getCourse();
			$course["description"] = $this->getDescription();
			return $course;
		}
	}
?>