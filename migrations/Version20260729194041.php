<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260729194041 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activity_log (id INT AUTO_INCREMENT NOT NULL, role VARCHAR(64) DEFAULT NULL, action VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, ip_address VARCHAR(64) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, raw_user_agent VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_FD06F647A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE admin_invite (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(255) NOT NULL, roles VARCHAR(255) NOT NULL, token VARCHAR(255) NOT NULL, expires_at DATETIME NOT NULL, accepted TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by_id INT NOT NULL, UNIQUE INDEX UNIQ_9A2A212E5F37A13B (token), INDEX IDX_9A2A212EB03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE admin_profile (id INT AUTO_INCREMENT NOT NULL, two_fasecret VARCHAR(255) DEFAULT NULL, is2_faverified TINYINT NOT NULL, is_disabled TINYINT NOT NULL, backup_codes_generated_at DATETIME DEFAULT NULL, failed_login_attempts INT NOT NULL, lock_until DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_456B2886A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE backup_code (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(255) NOT NULL, used TINYINT NOT NULL, admin_profile_id INT NOT NULL, INDEX IDX_EC15B5044C4B43D (admin_profile_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE booking (id INT AUTO_INCREMENT NOT NULL, rides JSON NOT NULL, passengers INT DEFAULT NULL, amount DOUBLE PRECISION NOT NULL, currency VARCHAR(255) NOT NULL, address JSON NOT NULL, status VARCHAR(255) NOT NULL, payment TINYINT NOT NULL, email VARCHAR(255) NOT NULL, booking_date DATETIME NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, ride_id INT DEFAULT NULL, INDEX IDX_E00CEDDEA76ED395 (user_id), INDEX IDX_E00CEDDE302A8A70 (ride_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE cart_item (id INT AUTO_INCREMENT NOT NULL, item_id VARCHAR(255) NOT NULL, quantity INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_F0FE2527A76ED395 (user_id), UNIQUE INDEX user_item_unique (user_id, item_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE commission_config (id INT AUTO_INCREMENT NOT NULL, rate DOUBLE PRECISION NOT NULL, effective_from DATETIME NOT NULL, active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE device (id INT AUTO_INCREMENT NOT NULL, fcm_token VARCHAR(255) NOT NULL, platform VARCHAR(255) NOT NULL, app_version VARCHAR(32) DEFAULT NULL, last_updated DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_92FB68E19B88AF9 (fcm_token), INDEX IDX_92FB68EA76ED395 (user_id), INDEX idx_device_last_updated (last_updated), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE driver_profile (id INT AUTO_INCREMENT NOT NULL, phone VARCHAR(32) NOT NULL, license_number VARCHAR(64) NOT NULL, vehicle_type VARCHAR(255) NOT NULL, model VARCHAR(128) NOT NULL, registration_number VARCHAR(64) NOT NULL, capacity INT NOT NULL, rating DOUBLE PRECISION NOT NULL, total_rides INT NOT NULL, status VARCHAR(255) NOT NULL, approved TINYINT NOT NULL, max_passengers INT NOT NULL, is_available TINYINT NOT NULL, documents JSON DEFAULT NULL, location_lat DOUBLE PRECISION DEFAULT NULL, location_lng DOUBLE PRECISION DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_44A8CE6FA76ED395 (user_id), INDEX idx_driver_location (location_lat, location_lng), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE fare_history (id INT AUTO_INCREMENT NOT NULL, previous_fare DOUBLE PRECISION DEFAULT NULL, updated_fare DOUBLE PRECISION DEFAULT NULL, calculated_expected_fare DOUBLE PRECISION DEFAULT NULL, updated_at DATETIME NOT NULL, ride_id INT NOT NULL, INDEX IDX_FF78996E302A8A70 (ride_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, job_id VARCHAR(255) DEFAULT NULL, title VARCHAR(255) NOT NULL, body LONGTEXT NOT NULL, message LONGTEXT DEFAULT NULL, data JSON DEFAULT NULL, extra JSON DEFAULT NULL, type VARCHAR(255) NOT NULL, scheduled_at DATETIME NOT NULL, sent_at DATETIME DEFAULT NULL, status VARCHAR(255) NOT NULL, attempts INT NOT NULL, last_error LONGTEXT DEFAULT NULL, is_read TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT DEFAULT NULL, ride_id INT DEFAULT NULL, booking_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_BF5476CABE04EA9 (job_id), INDEX IDX_BF5476CAA76ED395 (user_id), INDEX IDX_BF5476CA302A8A70 (ride_id), INDEX IDX_BF5476CA3301C60 (booking_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE otp (id INT AUTO_INCREMENT NOT NULL, otp VARCHAR(32) NOT NULL, expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_A79C98C1A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE rating (id INT AUTO_INCREMENT NOT NULL, score INT NOT NULL, comment LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, ride_id INT NOT NULL, rater_id INT NOT NULL, ratee_id INT NOT NULL, INDEX IDX_D8892622302A8A70 (ride_id), INDEX IDX_D88926223FC1CD0A (rater_id), INDEX IDX_D8892622F20FA22C (ratee_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE refresh_token (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(512) NOT NULL, revoked TINYINT NOT NULL, expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_C74F2195A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE ride (id INT AUTO_INCREMENT NOT NULL, pickup JSON NOT NULL, destination JSON NOT NULL, pickup_norm VARCHAR(255) NOT NULL, destination_norm VARCHAR(255) NOT NULL, price DOUBLE PRECISION NOT NULL, currency VARCHAR(255) NOT NULL, commission_rate DOUBLE PRECISION NOT NULL, commission_amount DOUBLE PRECISION NOT NULL, payout_amount DOUBLE PRECISION NOT NULL, description LONGTEXT NOT NULL, selected_date DATETIME NOT NULL, selected_time VARCHAR(64) NOT NULL, capacity INT NOT NULL, max_passengers INT NOT NULL, image_url VARCHAR(255) DEFAULT NULL, type VARCHAR(64) NOT NULL, status VARCHAR(255) NOT NULL, distance DOUBLE PRECISION DEFAULT NULL, duration VARCHAR(64) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, driver_id INT DEFAULT NULL, INDEX IDX_9B3D7CD0C3423909 (driver_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, password VARCHAR(255) DEFAULT NULL, avatar VARCHAR(255) DEFAULT NULL, google_id VARCHAR(255) DEFAULT NULL, verified TINYINT NOT NULL, reset_token VARCHAR(255) DEFAULT NULL, reset_token_expires DATETIME DEFAULT NULL, fcm_token VARCHAR(255) DEFAULT NULL, failed_login_attempts INT NOT NULL, lock_until DATETIME DEFAULT NULL, last_login_at DATETIME DEFAULT NULL, last_active_at DATETIME DEFAULT NULL, is_online TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), UNIQUE INDEX UNIQ_8D93D64976F5C865 (google_id), UNIQUE INDEX UNIQ_8D93D64919B88AF9 (fcm_token), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE user_role_assignment (id INT AUTO_INCREMENT NOT NULL, role VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_AD2D4219A76ED395 (user_id), UNIQUE INDEX user_role_unique (user_id, role), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_FD06F647A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE admin_invite ADD CONSTRAINT FK_9A2A212EB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE admin_profile ADD CONSTRAINT FK_456B2886A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE backup_code ADD CONSTRAINT FK_EC15B5044C4B43D FOREIGN KEY (admin_profile_id) REFERENCES admin_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDEA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDE302A8A70 FOREIGN KEY (ride_id) REFERENCES ride (id)');
        $this->addSql('ALTER TABLE cart_item ADD CONSTRAINT FK_F0FE2527A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE device ADD CONSTRAINT FK_92FB68EA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE driver_profile ADD CONSTRAINT FK_44A8CE6FA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE fare_history ADD CONSTRAINT FK_FF78996E302A8A70 FOREIGN KEY (ride_id) REFERENCES ride (id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA302A8A70 FOREIGN KEY (ride_id) REFERENCES ride (id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA3301C60 FOREIGN KEY (booking_id) REFERENCES booking (id)');
        $this->addSql('ALTER TABLE otp ADD CONSTRAINT FK_A79C98C1A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rating ADD CONSTRAINT FK_D8892622302A8A70 FOREIGN KEY (ride_id) REFERENCES ride (id)');
        $this->addSql('ALTER TABLE rating ADD CONSTRAINT FK_D88926223FC1CD0A FOREIGN KEY (rater_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE rating ADD CONSTRAINT FK_D8892622F20FA22C FOREIGN KEY (ratee_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE refresh_token ADD CONSTRAINT FK_C74F2195A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ride ADD CONSTRAINT FK_9B3D7CD0C3423909 FOREIGN KEY (driver_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE user_role_assignment ADD CONSTRAINT FK_AD2D4219A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity_log DROP FOREIGN KEY FK_FD06F647A76ED395');
        $this->addSql('ALTER TABLE admin_invite DROP FOREIGN KEY FK_9A2A212EB03A8386');
        $this->addSql('ALTER TABLE admin_profile DROP FOREIGN KEY FK_456B2886A76ED395');
        $this->addSql('ALTER TABLE backup_code DROP FOREIGN KEY FK_EC15B5044C4B43D');
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDEA76ED395');
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDE302A8A70');
        $this->addSql('ALTER TABLE cart_item DROP FOREIGN KEY FK_F0FE2527A76ED395');
        $this->addSql('ALTER TABLE device DROP FOREIGN KEY FK_92FB68EA76ED395');
        $this->addSql('ALTER TABLE driver_profile DROP FOREIGN KEY FK_44A8CE6FA76ED395');
        $this->addSql('ALTER TABLE fare_history DROP FOREIGN KEY FK_FF78996E302A8A70');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAA76ED395');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA302A8A70');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA3301C60');
        $this->addSql('ALTER TABLE otp DROP FOREIGN KEY FK_A79C98C1A76ED395');
        $this->addSql('ALTER TABLE rating DROP FOREIGN KEY FK_D8892622302A8A70');
        $this->addSql('ALTER TABLE rating DROP FOREIGN KEY FK_D88926223FC1CD0A');
        $this->addSql('ALTER TABLE rating DROP FOREIGN KEY FK_D8892622F20FA22C');
        $this->addSql('ALTER TABLE refresh_token DROP FOREIGN KEY FK_C74F2195A76ED395');
        $this->addSql('ALTER TABLE ride DROP FOREIGN KEY FK_9B3D7CD0C3423909');
        $this->addSql('ALTER TABLE user_role_assignment DROP FOREIGN KEY FK_AD2D4219A76ED395');
        $this->addSql('DROP TABLE activity_log');
        $this->addSql('DROP TABLE admin_invite');
        $this->addSql('DROP TABLE admin_profile');
        $this->addSql('DROP TABLE backup_code');
        $this->addSql('DROP TABLE booking');
        $this->addSql('DROP TABLE cart_item');
        $this->addSql('DROP TABLE commission_config');
        $this->addSql('DROP TABLE device');
        $this->addSql('DROP TABLE driver_profile');
        $this->addSql('DROP TABLE fare_history');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE otp');
        $this->addSql('DROP TABLE rating');
        $this->addSql('DROP TABLE refresh_token');
        $this->addSql('DROP TABLE ride');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE user_role_assignment');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
