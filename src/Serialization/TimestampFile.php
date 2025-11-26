<?php

namespace OpenTimestamps\Serialization;

use OpenTimestamps\Exception\SerializationException;

class TimestampFile {
    private array $ops = [];

    public function addOp(object $op): void {
        $this->ops[] = $op;
    }

    public function serialize(): string {
        $writer = new BinaryWriter();
        // Header bytes (example: magic + version)
        $writer->writeByte(0x4F); // 'O'
        $writer->writeByte(0x54); // 'T'
        $writer->writeByte(0x53); // 'S'
        $writer->writeByte(0x01); // version 1

        foreach ($this->ops as $op) {
            $writer->writeVarBytes($op->serialize());
        }

        return $writer->getData();
    }

    public static function deserialize(string $data): self {
        $reader = new BinaryReader($data);
        $magic = chr($reader->readByte()) . chr($reader->readByte()) . chr($reader->readByte());
        $version = $reader->readByte();
        if ($magic !== 'OTS' || $version !== 1) {
            throw new SerializationException('Invalid OTS file header');
        }

        $tsFile = new self();
        while (!$reader->eof()) {
            $opData = $reader->readVarBytes();
            // TODO: map opData to Op object
            $tsFile->addOp(new \stdClass()); // placeholder
        }

        return $tsFile;
    }
}