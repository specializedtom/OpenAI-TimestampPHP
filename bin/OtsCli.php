<?php

namespace OpenTimestamps\CLI;

use OpenTimestamps\TimestampFile\TimestampFile;
use OpenTimestamps\Ops\SHA256Op;
use OpenTimestamps\Ops\CalendarCommitOp;

class OtsCli {

    public static function stampFile(string $filePath, string $otsPath, string $calendarUrl = ''): void {
        if (!file_exists($filePath)) {
            fwrite(STDERR, "File not found: $filePath\n");
            exit(1);
        }

        $contents = file_get_contents($filePath);
        $fileHash = hash('sha256', $contents, true);

        $tsFile = new TimestampFile([new SHA256Op()]);

        if ($calendarUrl !== '') {
            $calendar = new \OpenTimestamps\Calendar\CalendarClient($calendarUrl);
            $tsFile = $calendar->stamp($tsFile);
        }

        file_put_contents($otsPath, $tsFile->serialize());
        echo "Saved TimestampFile to $otsPath\n";
    }

    public static function verifyFile(string $filePath, string $otsPath): bool {
        if (!file_exists($filePath) || !file_exists($otsPath)) {
            fwrite(STDERR, "File or .ots not found\n");
            return false;
        }

        $contents = file_get_contents($filePath);
        $tsFileData = file_get_contents($otsPath);
        $tsFile = TimestampFile::deserialize($tsFileData);

        $leafHash = hash('sha256', $contents, true);
        echo "Computed leaf hash: " . bin2hex($leafHash) . "\n";

        $valid = true;
        foreach ($tsFile->getOps() as $op) {
            if ($op instanceof CalendarCommitOp) {
                if (!self::verifyCalendarCommitOp($op, $leafHash)) {
                    $valid = false;
                    echo "Calendar attestation verification failed\n";
                }
            }
        }

        if ($valid) {
            echo "All CalendarCommitOp attestations verified successfully.\n";
        }

        return $valid;
    }

    private static function verifyCalendarCommitOp(CalendarCommitOp $op, string $leafHash): bool {
        $attestation = $op->serialize();
        if (empty($attestation)) {
            return false;
        }

        $proofNodes = self::parseMerkleProof($attestation);
        $computedRoot = $leafHash;
        foreach ($proofNodes as $siblingHash) {
            $computedRoot = hash('sha256', $computedRoot . $siblingHash, true);
        }

        $attestedRoot = self::extractRootFromAttestation($attestation);
        return $computedRoot === $attestedRoot;
    }

    private static function parseMerkleProof(string $attestation): array {
        $offset = 0;
        $proof = [];

        while ($offset < strlen($attestation)) {
            $proof[] = substr($attestation, $offset, 32); // 32 bytes SHA256 sibling
            $offset += 32;
        }

        return $proof;
    }

    private static function extractRootFromAttestation(string $attestation): string {
        // Last 32 bytes assumed to be the root hash (depends on OTS format)
        return substr($attestation, -32);
    }
}
