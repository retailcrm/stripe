<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201002074456 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE i_account (id UUID NOT NULL, integration_id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT \'now()\' NOT NULL, deactivated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, name VARCHAR(255) NOT NULL, account_id VARCHAR(255) NOT NULL, test BOOLEAN DEFAULT \'false\' NOT NULL, approve_manually BOOLEAN DEFAULT \'false\' NOT NULL, public_key VARCHAR(255) DEFAULT NULL, secret_key VARCHAR(255) DEFAULT NULL, locale VARCHAR(10) DEFAULT \'en_US\' NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX i_integration_id_idx ON i_account (integration_id)');
        $this->addSql('CREATE UNIQUE INDEX i_integration_id_account_id_idx ON i_account (integration_id, account_id)');
        $this->addSql('COMMENT ON COLUMN i_account.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN i_account.integration_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE i_integration (id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT \'now()\' NOT NULL, crm_url VARCHAR(255) NOT NULL, crm_api_key VARCHAR(255) NOT NULL, active BOOLEAN DEFAULT \'true\' NOT NULL, freezed BOOLEAN DEFAULT \'false\' NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX i_integration_crm_url_api_key_idx ON i_integration (crm_url, crm_api_key)');
        $this->addSql('COMMENT ON COLUMN i_integration.id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE i_payment (id VARCHAR(255) NOT NULL, account_id UUID NOT NULL, url_id INT NOT NULL, status VARCHAR(255) DEFAULT NULL, paid BOOLEAN DEFAULT \'false\' NOT NULL, amount VARCHAR(255) DEFAULT NULL, refunded_amount VARCHAR(255) DEFAULT NULL, currency VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, captured_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, test BOOLEAN DEFAULT \'false\' NOT NULL, cancel_on_waiting_capture BOOLEAN DEFAULT \'false\' NOT NULL, invoice_uuid UUID NOT NULL, payment_uuid UUID NOT NULL, session_id VARCHAR(255) NOT NULL, cancellation_details VARCHAR(255) DEFAULT NULL, refundable BOOLEAN DEFAULT \'true\' NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_6A8B99489B6B5FBA ON i_payment (account_id)');
        $this->addSql('CREATE INDEX IDX_6A8B994881CFDAE7 ON i_payment (url_id)');
        $this->addSql('COMMENT ON COLUMN i_payment.account_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN i_payment.invoice_uuid IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN i_payment.payment_uuid IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE i_refund (id VARCHAR(255) NOT NULL, payment_id VARCHAR(255) NOT NULL, status VARCHAR(255) DEFAULT NULL, amount VARCHAR(255) DEFAULT NULL, currency VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, comment VARCHAR(255) DEFAULT NULL, from_notification BOOLEAN DEFAULT \'false\' NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_95F5485C4C3A3BB ON i_refund (payment_id)');
        $this->addSql('CREATE TABLE i_stripe_notification (id UUID NOT NULL, payment_id VARCHAR(255) DEFAULT NULL, response TEXT DEFAULT NULL, event VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_98178C6D4C3A3BB ON i_stripe_notification (payment_id)');
        $this->addSql('COMMENT ON COLUMN i_stripe_notification.id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE i_stripe_webhook (id UUID NOT NULL, account_id UUID DEFAULT NULL, webhook VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT \'now()\' NOT NULL, secret VARCHAR(255) NOT NULL, url VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX i_account_id_idx ON i_stripe_webhook (account_id)');
        $this->addSql('COMMENT ON COLUMN i_stripe_webhook.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN i_stripe_webhook.account_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE i_url (id SERIAL NOT NULL, account_id UUID DEFAULT NULL, slug TEXT NOT NULL, request JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, canceled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_CA5961699B6B5FBA ON i_url (account_id)');
        $this->addSql('CREATE UNIQUE INDEX i_url_slug_idx ON i_url (slug)');
        $this->addSql('COMMENT ON COLUMN i_url.account_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN i_url.request IS \'(DC2Type:json_array)\'');
        $this->addSql('ALTER TABLE i_account ADD CONSTRAINT FK_7A954BE19E82DDEA FOREIGN KEY (integration_id) REFERENCES i_integration (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE i_payment ADD CONSTRAINT FK_6A8B99489B6B5FBA FOREIGN KEY (account_id) REFERENCES i_account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE i_payment ADD CONSTRAINT FK_6A8B994881CFDAE7 FOREIGN KEY (url_id) REFERENCES i_url (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE i_refund ADD CONSTRAINT FK_95F5485C4C3A3BB FOREIGN KEY (payment_id) REFERENCES i_payment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE i_stripe_notification ADD CONSTRAINT FK_98178C6D4C3A3BB FOREIGN KEY (payment_id) REFERENCES i_payment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE i_stripe_webhook ADD CONSTRAINT FK_A698B0C89B6B5FBA FOREIGN KEY (account_id) REFERENCES i_account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE i_url ADD CONSTRAINT FK_CA5961699B6B5FBA FOREIGN KEY (account_id) REFERENCES i_account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE i_payment DROP CONSTRAINT FK_6A8B99489B6B5FBA');
        $this->addSql('ALTER TABLE i_stripe_webhook DROP CONSTRAINT FK_A698B0C89B6B5FBA');
        $this->addSql('ALTER TABLE i_url DROP CONSTRAINT FK_CA5961699B6B5FBA');
        $this->addSql('ALTER TABLE i_account DROP CONSTRAINT FK_7A954BE19E82DDEA');
        $this->addSql('ALTER TABLE i_refund DROP CONSTRAINT FK_95F5485C4C3A3BB');
        $this->addSql('ALTER TABLE i_stripe_notification DROP CONSTRAINT FK_98178C6D4C3A3BB');
        $this->addSql('ALTER TABLE i_payment DROP CONSTRAINT FK_6A8B994881CFDAE7');
        $this->addSql('DROP TABLE i_account');
        $this->addSql('DROP TABLE i_integration');
        $this->addSql('DROP TABLE i_payment');
        $this->addSql('DROP TABLE i_refund');
        $this->addSql('DROP TABLE i_stripe_notification');
        $this->addSql('DROP TABLE i_stripe_webhook');
        $this->addSql('DROP TABLE i_url');
    }
}
