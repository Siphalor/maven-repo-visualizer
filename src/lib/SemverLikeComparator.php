<?php

namespace MavenRV;

class SemverLikeComparator
{
    public static function compare(string $a, string $b): int
    {
        $offset = 0;
        while (true) {
            $aPart = self::find_next_part($a, $offset, '.', '-', '+');
            $bPart = self::find_next_part($b, $offset, '.', '-', '+');
            $cmp = self::part_cmp($aPart[1], $bPart[1]);
            if ($cmp !== 0) {
                return $cmp;
            } elseif ($aPart[0] < 0 && $bPart[0] < 0) {
                return 0;
            }
            $aOp = array_search($aPart[2], ['-', '+', '', '.']);
            $bOp = array_search($bPart[2], ['-', '+', '', '.']);
            $cmp = $aOp <=> $bOp;
            if ($cmp !== 0) {
                return $cmp;
            }
            $offset = $aPart[0] + 1;
            if ($aPart[2] === '-') {
                return self::compare_in_pre_release(substr($a, $offset), substr($b, $offset));
            } elseif ($aPart[2] === '+') {
                return self::compare_in_build_metadata(substr($a, $offset), substr($b, $offset));
            }
        }
    }

    private static function compare_in_pre_release(string $a, string $b): int
    {
        $offset = 0;
        while (true) {
            $aPart = self::find_next_part($a, $offset, '.', '+');
            $bPart = self::find_next_part($b, $offset, '.', '+');
            $cmp = self::part_cmp($aPart[1], $bPart[1]);
            if ($cmp !== 0) {
                return $cmp;
            } elseif ($aPart[0] < 0 && $bPart[0] < 0) {
                return 0;
            }
            $aOp = array_search($aPart[2], ['+', '', '.']);
            $bOp = array_search($bPart[2], ['+', '', '.']);
            $cmp = $aOp <=> $bOp;
            if ($cmp !== 0) {
                return $cmp;
            }
            $offset = $aPart[0] + 1;
            if ($aPart[2] === '+') {
                return self::compare_in_build_metadata(substr($a, $offset), substr($b, $offset));
            }
        }
    }

    private static function compare_in_build_metadata(string $a, string $b): int
    {
        $offset = 0;
        while (true) {
            $aPart = self::find_next_part($a, $offset, '.');
            $bPart = self::find_next_part($b, $offset, '.');
            $cmp = self::part_cmp($aPart[1], $bPart[1]);
            if ($cmp !== 0) {
                return $cmp;
            } elseif ($aPart[0] < 0 && $bPart[0] < 0) {
                return 0;
            }
            $aOp = array_search($aPart[2], ['.', '']);
            $bOp = array_search($bPart[2], ['.', '']);
            $cmp = $aOp <=> $bOp;
            if ($cmp !== 0) {
                return $cmp;
            }
            $offset = $aPart[0] + 1;
        }
    }

    private static function find_next_part(string $str, int $start, string... $chars): array
    {
        for ($i = $start; $i < strlen($str); $i++) {
            if (in_array($str[$i], $chars)) {
                return [$i, substr($str, $start, $i - $start), $str[$i]];
            }
        }
        return [-1, substr($str, $start), ''];
    }

    private static function part_cmp(string $aPart, string $bPart): int
    {
        $aDigits = ctype_digit($aPart);
        $bDigits = ctype_digit($bPart);
        if ($aDigits && $bDigits) {
            return intval($aPart) <=> intval($bPart);
        } elseif ($aDigits && !$bDigits) {
            return -1;
        } elseif (!$aDigits && $bDigits) {
            return 1;
        } else {
            return strcmp($aPart, $bPart);
        }
    }
}
