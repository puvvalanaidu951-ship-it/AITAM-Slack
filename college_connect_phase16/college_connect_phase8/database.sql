CREATE DATABASE IF NOT EXISTS college_connect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE college_connect;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('student','alumni','faculty','authority','prospective','admin') NOT NULL DEFAULT 'student',
  department VARCHAR(120) NULL,
  graduation_year YEAR NULL,
  affiliation_verified TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','suspended','pending') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  description VARCHAR(255) NOT NULL,
  icon VARCHAR(12) NOT NULL DEFAULT '•',
  color VARCHAR(20) NOT NULL DEFAULT 'violet',
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS questions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  category_id INT UNSIGNED NOT NULL,
  title VARCHAR(220) NOT NULL,
  body TEXT NOT NULL,
  moderation_status ENUM('pending','allowed','review','blocked') NOT NULL DEFAULT 'allowed',
  status ENUM('draft','pending_review','published','rejected','archived') NOT NULL DEFAULT 'published',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_questions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_questions_category FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS answers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  parent_answer_id BIGINT UNSIGNED NULL,
  body TEXT NOT NULL,
  status ENUM('pending_review','published','hidden','blocked') NOT NULL DEFAULT 'published',
  is_accepted TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_answers_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
  CONSTRAINT fk_answers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_answers_parent FOREIGN KEY (parent_answer_id) REFERENCES answers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS announcements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  body TEXT NOT NULL,
  label VARCHAR(40) NOT NULL DEFAULT 'COMMUNITY',
  created_by BIGINT UNSIGNED NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_announcements_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS moderation_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  content_type ENUM('question','answer','comment') NOT NULL,
  content_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(80) NULL,
  decision ENUM('allow','review','block') NOT NULL,
  categories_json JSON NULL,
  confidence DECIMAL(5,4) NULL,
  reason VARCHAR(500) NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_moderation_decision (decision, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reporter_id BIGINT UNSIGNED NULL,
  content_type ENUM('question','answer','user') NOT NULL,
  content_id BIGINT UNSIGNED NOT NULL,
  reason VARCHAR(100) NOT NULL,
  details TEXT NULL,
  status ENUM('open','reviewing','resolved','dismissed') NOT NULL DEFAULT 'open',
  reviewed_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reports_reporter FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_reports_status (status, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(50) NOT NULL,
  title VARCHAR(180) NOT NULL,
  message VARCHAR(500) NOT NULL,
  related_type VARCHAR(50) NULL,
  related_id BIGINT UNSIGNED NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_notifications_user_read (user_id, is_read, created_at)
) ENGINE=InnoDB;

INSERT INTO categories (name,slug,description,icon,color,sort_order) VALUES
('Admissions','admissions','Eligibility, applications, and joining the college.','admissions','violet',1),
('Academics','academics','Courses, exams, projects, and study support.','academics','blue',2),
('Placements','placements','Career guidance, interviews, and opportunities.','placements','mint',3),
('Campus Life','campus-life','Facilities, transport, clubs, and student life.','campus-life','orange',4)
ON DUPLICATE KEY UPDATE description=VALUES(description), icon=VALUES(icon), color=VALUES(color), sort_order=VALUES(sort_order);

INSERT INTO questions (user_id,category_id,title,body,moderation_status,status,created_at) VALUES
(NULL,1,'What is the admission process for the CSE department?','Can someone explain the eligibility requirements and where to find the latest official admission notification?','allowed','published',DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(NULL,3,'How can I prepare for technical placement interviews?','I am starting my placement preparation. Which data structures, projects, and practice routines should I focus on first?','allowed','published',DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(NULL,2,'Where can students find the semester exam timetable?','Looking for the official place to check updates about semester examinations and academic schedules.','allowed','published',DATE_SUB(NOW(), INTERVAL 1 DAY)),
(NULL,4,'What are the library timings during exam week?','Does the library extend its opening hours during internal and semester examinations?','allowed','published',DATE_SUB(NOW(), INTERVAL 2 DAY)),
(NULL,3,'Which skills are useful for an AI/ML internship?','I want to apply for internships and would appreciate advice on Python, machine learning fundamentals, and project portfolios.','allowed','published',DATE_SUB(NOW(), INTERVAL 3 DAY));

INSERT INTO answers (question_id,user_id,body,status) VALUES
(1,NULL,'Please check the official college or admission authority notification for the latest eligibility criteria and application procedure.','published'),
(1,NULL,'Keep your academic documents and application details ready, and verify dates from official sources.','published'),
(1,NULL,'You can also contact the admissions office for confirmation.','published'),
(2,NULL,'Build a consistent routine for data structures and algorithms, and practice explaining the projects you have completed.','published'),
(2,NULL,'Use mock interviews and revise core CS fundamentals alongside coding practice.','published'),
(2,NULL,'Keep your resume focused on projects where you can clearly explain your own contribution.','published'),
(2,NULL,'Practice arrays, strings, hashing, trees, and problem-solving patterns.','published'),
(2,NULL,'Review DBMS, operating systems, networking, and OOP fundamentals.','published'),
(2,NULL,'Ask seniors and alumni about their interview experiences, while verifying company-specific details.','published'),
(3,NULL,'Check the official department notice board or college website for current exam schedules.','published'),
(3,NULL,'Confirm the timetable with your department if you see conflicting copies.','published'),
(4,NULL,'Library hours can vary during exams; confirm the current schedule with library staff.','published'),
(4,NULL,'Look for an official campus announcement before planning a late study session.','published'),
(4,NULL,'If hours are extended, the college should publish the confirmed timings.','published'),
(4,NULL,'You can ask the library desk for the latest information.','published'),
(5,NULL,'Start with Python, basic statistics, data preparation, and core supervised learning concepts.','published'),
(5,NULL,'Create a small end-to-end project and document the data, evaluation, and limitations.','published'),
(5,NULL,'Learn to explain model choices and metrics, not just demonstrate a notebook.','published'),
(5,NULL,'A clean GitHub repository with a clear README can help reviewers understand your work.','published'),
(5,NULL,'Explore internships that match your current skill level and keep improving fundamentals.','published');

INSERT INTO announcements (title,body,label,is_published,created_at) VALUES
('Welcome to AITAM SLACK','A shared space for questions, answers, campus knowledge, and community support.','COMMUNITY',1,DATE_SUB(NOW(), INTERVAL 1 HOUR)),
('Check official academic updates','For exam dates and eligibility requirements, always confirm details with official college notices.','ACADEMICS',1,DATE_SUB(NOW(), INTERVAL 1 DAY)),
('Help the next student','Share helpful, respectful answers so useful knowledge is easier for everyone to find.','COMMUNITY',1,DATE_SUB(NOW(), INTERVAL 2 DAY));
