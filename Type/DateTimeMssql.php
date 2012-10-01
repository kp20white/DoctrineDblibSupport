<?php

namespace FAMC\Bundle\DoctrineDblibSupportBundle\Type;

use Doctrine\DBAL\Types\Type,
    Doctrine\DBAL\Platforms\AbstractPlatform
;

class DateTimeMssql extends Type {
    const DATETIME_MSSQL = 'datetime_mssql';
    public function getName() {
        return self::DATETIME_MSSQL;
    }

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform) {
        return "DateTimeMssql";
    }

    public function convertToPHPValue($value, AbstractPlatform $platform) {
        $datetime = new \DateTime($value);
        return $datetime->format('m/d/Y H:m:s');
    }

    public function convertToPHPValueSQL($sqlExpr, $platform) {
        return parent::convertToPHPValueSQL($sqlExpr, $platform);
    }

}
