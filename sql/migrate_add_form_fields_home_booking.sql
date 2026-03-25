-- Migration: add admin-editable form_title and form_paragraph to home_booking
ALTER TABLE `home_booking`
  ADD COLUMN `form_title` VARCHAR(255) DEFAULT 'Request Your Personalized Plan',
  ADD COLUMN `form_paragraph` TEXT;

-- Safe: additive, won't drop existing columns or data.
-- Run this once in the target DB environment.
