<?php

namespace OpenTimestamps\Ops;

use OpenTimestamps\Serialization\BinaryReader;
use OpenTimestamps\Serialization\BinaryWriter;

/**
 * Represents a Bitcoin block header attestation.
 * OTS OP CODE: 0x14
 */
class BitcoinBlockHeaderOp extends Op
{
    public const OPCODE = 0x14;

    private string $blockHeader; // 80-byte Bitcoin block header
    private string $txHash;      // Optional: hash of the tx (32 bytes) that includes the OP_RETURN

    public function __construct(string $blockHeader, string $txHash = '')
    {
        if (strlen($blockHeader) !== 80) {
            throw new \InvalidArgumentException('Bitcoin block header must be 80 bytes');
        }
        if ($txHash !== '' && strlen($txHash) !== 32) {
            throw new \InvalidArgumentException('Tx hash must be 32 bytes');
        }

        $this->blockHeader = $blockHeader;
        $this->txHash = $txHash;
    }

    public function getBlockHeader(): string
    {
        return $this->blockHeader;
    }

    public function getTxHash(): string
    {
        return $this->txHash;
    }

    public function apply(string $data): string
    {
        // Bitcoin block header doesn’t change the digest, it just proves existence
        return $data;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeVarInt(self::OPCODE);
        $writer->writeVarBytes($this->blockHeader);
        $writer->writeVarBytes($this->txHash);
    }

    public static function deserialize(BinaryReader $reader): self
    {
        $blockHeader = $reader->readVarBytes();
        $txHash = $reader->readVarBytes();
        return new self($blockHeader, $txHash);
    }
}
