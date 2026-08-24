-- The PHPUnit suite runs against its own schema (see phpunit.xml) so tests
-- never touch development data.
CREATE DATABASE IF NOT EXISTS absensi_testing;
GRANT ALL PRIVILEGES ON absensi_testing.* TO 'root'@'%';
FLUSH PRIVILEGES;
