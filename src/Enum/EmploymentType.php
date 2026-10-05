<?php

declare(strict_types=1);

namespace App\Enum;

enum EmploymentType: string
{
    case FullTime = 'full_time';
    case PartTime = 'part_time';
    case Minijob = 'minijob';
    case Apprenticeship = 'apprenticeship';
    case Internship = 'internship';
    case WorkingStudent = 'working_student';
    case Temporary = 'temporary';

    public function label(): string
    {
        return match ($this) {
            self::FullTime => 'Vollzeit',
            self::PartTime => 'Teilzeit',
            self::Minijob => 'Minijob',
            self::Apprenticeship => 'Ausbildung',
            self::Internship => 'Praktikum',
            self::WorkingStudent => 'Werkstudent',
            self::Temporary => 'Befristet',
        };
    }

    public function schemaValue(): string
    {
        return match ($this) {
            self::FullTime => 'FULL_TIME',
            self::PartTime => 'PART_TIME',
            self::Minijob => 'PART_TIME',
            self::Apprenticeship => 'OTHER',
            self::Internship => 'INTERN',
            self::WorkingStudent => 'PART_TIME',
            self::Temporary => 'TEMPORARY',
        };
    }
}
