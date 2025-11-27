<?php

namespace OpenTimestamps\Op;

use OpenTimestamps\Serialization\BinaryReader;
use OpenTimestamps\Serialization\BinaryWriter;

class CalendarCommitOp extends Op {
    public const OPCODE = 0x06;
    private string $attestation;

    public function __construct(string $attestation) {
        $this->attestation = $attestation;
    }

    public function apply(string $input): string {
        return $input; // Calendar commit does not alter digest
    }

    public function serialize(): string {
        $writer = new BinaryWriter();
        $writer->writeByte(self::OPCODE);
        $writer->writeVarBytes($this->attestation);
        return $writer->getData();
    }

    public static function fromData(BinaryReader $reader): self {
        $attestation = $reader->readVarBytes();
        return new self($attestation);
    }
}
