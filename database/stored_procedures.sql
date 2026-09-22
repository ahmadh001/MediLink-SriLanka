USE `patient_doctor_booking`;

DELIMITER $$
DROP PROCEDURE IF EXISTS `sp_BookAppointment`$$
CREATE PROCEDURE `sp_BookAppointment`(
    IN  p_client_id      INT,
    IN  p_slot_id        INT,
    IN  p_notes          TEXT,
    OUT p_status         VARCHAR(50),
    OUT p_appointment_id INT
)
proc_label: BEGIN
    DECLARE v_user_id INT DEFAULT NULL;
    DECLARE v_slot_status VARCHAR(20) DEFAULT NULL;
    DECLARE v_plan_id INT DEFAULT NULL;
    DECLARE v_max_bookings INT DEFAULT 0;
    DECLARE v_used_bookings INT DEFAULT 0;
    DECLARE v_slot_exists INT DEFAULT 0;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_status = 'SQL_ERROR';
        SET p_appointment_id = NULL;
    END;
    SELECT `User_ID` INTO v_user_id 
    FROM `CLIENT` 
    WHERE `Client_ID` = p_client_id 
    LIMIT 1;

    IF v_user_id IS NULL THEN
        SET p_status = 'CLIENT_NOT_FOUND';
        SET p_appointment_id = NULL;
        LEAVE proc_label;
    END IF;
    SELECT us.`Plan_ID`, sp.`Max_Book_per_Month`
    INTO v_plan_id, v_max_bookings
    FROM `USER_SUBSCRIPTION` us
    JOIN `SUBSCRIPTION_PLAN` sp ON us.`Plan_ID` = sp.`Plan_ID`
    WHERE us.`User_ID` = v_user_id
      AND us.`Status` = 'ACTIVE'
      AND CURRENT_DATE BETWEEN us.`Start_Date` AND us.`End_Date`
    ORDER BY us.`End_Date` DESC
    LIMIT 1;

    IF v_plan_id IS NULL THEN
        SET p_status = 'NO_ACTIVE_SUBSCRIPTION';
        SET p_appointment_id = NULL;
        LEAVE proc_label;
    END IF;
    SELECT COUNT(*) INTO v_used_bookings
    FROM `APPOINTMENT`
    WHERE `Client_ID` = p_client_id
      AND MONTH(`Booking_DateTime`) = MONTH(CURRENT_DATE)
      AND YEAR(`Booking_DateTime`) = YEAR(CURRENT_DATE)
      AND `Status` IN ('BOOKED', 'CONFIRMED', 'COMPLETED');

    IF v_used_bookings >= v_max_bookings THEN
        SET p_status = 'QUOTA_EXCEEDED';
        SET p_appointment_id = NULL;
        LEAVE proc_label;
    END IF;
    START TRANSACTION;
    SELECT `Status` INTO v_slot_status
    FROM `SCHEDULED_SLOT`
    WHERE `Slot_ID` = p_slot_id
    FOR UPDATE;
    IF v_slot_status IS NULL THEN
        ROLLBACK;
        SET p_status = 'SLOT_NOT_FOUND';
        SET p_appointment_id = NULL;
        LEAVE proc_label;
    ELSEIF v_slot_status <> 'AVAILABLE' THEN
        ROLLBACK;
        SET p_status = 'SLOT_UNAVAILABLE';
        SET p_appointment_id = NULL;
        LEAVE proc_label;
    END IF;
    INSERT INTO `APPOINTMENT` (
        `Client_ID`, 
        `Slot_ID`, 
        `Booking_DateTime`, 
        `Status`, 
        `Notes`
    ) VALUES (
        p_client_id, 
        p_slot_id, 
        NOW(), 
        'CONFIRMED', 
        p_notes
    );

    SET p_appointment_id = LAST_INSERT_ID();
    UPDATE `SCHEDULED_SLOT` 
    SET `Status` = 'BOOKED' 
    WHERE `Slot_ID` = p_slot_id;
    COMMIT;
    SET p_status = 'SUCCESS';

END proc_label$$
DROP PROCEDURE IF EXISTS `sp_CancelAppointment`$$
CREATE PROCEDURE `sp_CancelAppointment`(
    IN  p_appointment_id INT,
    IN  p_client_id      INT,
    OUT p_status         VARCHAR(50)
)
proc_label: BEGIN
    DECLARE v_slot_id INT DEFAULT NULL;
    DECLARE v_current_status VARCHAR(20) DEFAULT NULL;
    DECLARE v_appt_client_id INT DEFAULT NULL;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_status = 'SQL_ERROR';
    END;
    SELECT `Slot_ID`, `Status`, `Client_ID` 
    INTO v_slot_id, v_current_status, v_appt_client_id
    FROM `APPOINTMENT`
    WHERE `Appointment_ID` = p_appointment_id
    LIMIT 1;

    IF v_slot_id IS NULL THEN
        SET p_status = 'APPOINTMENT_NOT_FOUND';
        LEAVE proc_label;
    END IF;
    IF p_client_id IS NOT NULL AND v_appt_client_id <> p_client_id THEN
        SET p_status = 'UNAUTHORIZED';
        LEAVE proc_label;
    END IF;
    IF v_current_status = 'CANCELLED' THEN
        SET p_status = 'ALREADY_CANCELLED';
        LEAVE proc_label;
    ELSEIF v_current_status = 'COMPLETED' THEN
        SET p_status = 'CANNOT_CANCEL_COMPLETED';
        LEAVE proc_label;
    END IF;
    START TRANSACTION;
    UPDATE `APPOINTMENT` 
    SET `Status` = 'CANCELLED', `Updated_At` = NOW()
    WHERE `Appointment_ID` = p_appointment_id;
    UPDATE `SCHEDULED_SLOT`
    SET `Status` = 'AVAILABLE'
    WHERE `Slot_ID` = v_slot_id;

    COMMIT;
    SET p_status = 'SUCCESS';

END proc_label$$
DROP PROCEDURE IF EXISTS `sp_GetDoctorAvailableSlots`$$
CREATE PROCEDURE `sp_GetDoctorAvailableSlots`(
    IN p_doctor_id  INT,
    IN p_start_date DATE,
    IN p_end_date   DATE
)
BEGIN
    SELECT 
        s.`Slot_ID`,
        s.`Slot_Date`,
        s.`Start_Time`,
        s.`End_Time`,
        s.`Status`,
        p.`Business_Name` AS `Clinic_Name`,
        p.`City` AS `Clinic_City`,
        p.`Address` AS `Clinic_Address`,
        CONCAT(u.`First_Name`, ' ', u.`Last_Name`) AS `Doctor_Name`,
        d.`Medical_License_No`,
        d.`Consultation_Duration`
    FROM `SCHEDULED_SLOT` s
    JOIN `PROVIDER` p ON s.`Provider_ID` = p.`Provider_ID`
    LEFT JOIN `DOCTOR` d ON s.`Doctor_ID` = d.`Doctor_ID`
    LEFT JOIN `PROVIDER` dp ON d.`Provider_ID` = dp.`Provider_ID`
    LEFT JOIN `USER` u ON dp.`User_ID` = u.`User_ID`
    WHERE (s.`Doctor_ID` = p_doctor_id OR (s.`Doctor_ID` IS NULL AND p.`Provider_ID` = (SELECT `Provider_ID` FROM `DOCTOR` WHERE `Doctor_ID` = p_doctor_id)))
      AND s.`Slot_Date` BETWEEN p_start_date AND p_end_date
      AND s.`Status` = 'AVAILABLE'
    ORDER BY s.`Slot_Date` ASC, s.`Start_Time` ASC;
END$$
DROP PROCEDURE IF EXISTS `sp_GetClientSubscriptionSummary`$$
CREATE PROCEDURE `sp_GetClientSubscriptionSummary`(
    IN  p_client_id          INT,
    OUT p_has_active_plan    INT,
    OUT p_plan_name          VARCHAR(100),
    OUT p_days_remaining     INT,
    OUT p_monthly_quota      INT,
    OUT p_bookings_used      INT,
    OUT p_bookings_remaining INT
)
BEGIN
    DECLARE v_user_id INT DEFAULT NULL;
    DECLARE v_plan_id INT DEFAULT NULL;
    DECLARE v_end_date DATE DEFAULT NULL;
    SET p_has_active_plan = 0;
    SET p_plan_name = 'No Active Subscription';
    SET p_days_remaining = 0;
    SET p_monthly_quota = 0;
    SET p_bookings_used = 0;
    SET p_bookings_remaining = 0;
    SELECT `User_ID` INTO v_user_id 
    FROM `CLIENT` 
    WHERE `Client_ID` = p_client_id 
    LIMIT 1;

    IF v_user_id IS NOT NULL THEN
        SELECT 
            sp.`Plan_Name`,
            DATEDIFF(us.`End_Date`, CURRENT_DATE),
            sp.`Max_Book_per_Month`,
            us.`End_Date`
        INTO 
            p_plan_name,
            p_days_remaining,
            p_monthly_quota,
            v_end_date
        FROM `USER_SUBSCRIPTION` us
        JOIN `SUBSCRIPTION_PLAN` sp ON us.`Plan_ID` = sp.`Plan_ID`
        WHERE us.`User_ID` = v_user_id
          AND us.`Status` = 'ACTIVE'
          AND CURRENT_DATE BETWEEN us.`Start_Date` AND us.`End_Date`
        ORDER BY us.`End_Date` DESC
        LIMIT 1;

        IF v_end_date IS NOT NULL THEN
            SET p_has_active_plan = 1;
            SELECT COUNT(*) INTO p_bookings_used
            FROM `APPOINTMENT`
            WHERE `Client_ID` = p_client_id
              AND MONTH(`Booking_DateTime`) = MONTH(CURRENT_DATE)
              AND YEAR(`Booking_DateTime`) = YEAR(CURRENT_DATE)
              AND `Status` IN ('BOOKED', 'CONFIRMED', 'COMPLETED');
            IF p_monthly_quota > p_bookings_used THEN
                SET p_bookings_remaining = p_monthly_quota - p_bookings_used;
            ELSE
                SET p_bookings_remaining = 0;
            END IF;
        END IF;
    END IF;
END$$

DELIMITER ;
