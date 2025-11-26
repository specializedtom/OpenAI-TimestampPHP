<?php

use OpenTimestamps\Serialization\BinaryReader;
use OpenTimestamps\Serialization\BinaryWriter;
use OpenTimestamps\Exception\SerializationException;

class CalendarCommitOp extends Op {
    public const OPCODE = 0x06;
    private string $attestation;

    public function __construct(string $attestation) {
        $this->attestation = $attestation;
    }

    public function apply(string $input): string {
        return $input;
    }

    public function serialize(): string {
        $writer = new BinaryWriter();
        $writer->writeByte(self::OPCODE);
        $writer->writeVarBytes($this->attestation);
        return $writer->getData();
    }

    public static function fromData(string $data): self {
        $reader = new BinaryReader(substr($data, 1));
        return new self($reader->readVarBytes());
    }
}