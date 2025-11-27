<?php

namespace OpenTimestamps\Op;

use OpenTimestamps\Serialization\BinaryReader;
use OpenTimestamps\Serialization\BinaryWriter;

class BitcoinBlockHeaderOp extends Op {
    public const OPCODE = 0x10;
    private string $blockHeader;
    private string $txHash;

    public function __construct(string $blockHeader, string $txHash) {
        $this->blockHeader = $blockHeader;
        $this->txHash = $txHash;
    }

    public function apply(string $input): string {
        // Typically used for SPV verification, may leave digest unchanged
        return $input;
    }

    public function serialize(): string {
        $writer = new BinaryWriter();
        $writer->writeByte(self::OPCODE);
        $writer->writeVarBytes($this->blockHeader);
        $writer->writeVarBytes($this->txHash);
        return $writer->getData();
    }

    public static function fromData(BinaryReader $reader): self {
        $blockHeader = $reader->readVarBytes();
        $txHash = $reader->readVarBytes();
        return new self($blockHeader, $txHash);
    }
}

