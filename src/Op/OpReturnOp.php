<?php

namespace OpenTimestamps\Ops;

use OpenTimestamps\Serialization\BinaryReader;
use OpenTimestamps\Serialization\BinaryWriter;

/**
 * OP_RETURN commitment in Bitcoin.
 * OTS OP CODE: 0x13
 */
class OpReturnOp extends Op
{
    public const OPCODE = 0x13;

    private string $commitment; // raw hash committed in OP_RETURN

    public function __construct(string $commitment)
    {
        if (strlen($commitment) !== 32) {
            throw new \InvalidArgumentException('OP_RETURN commitment must be 32 bytes (SHA256)');
        }
        $this->commitment = $commitment;
    }

    public function getCommitment(): string
    {
        return $this->commitment;
    }

    public function apply(string $data): string
    {
        // OP_RETURN doesn’t alter the leaf; it’s just a commitment
        return $data;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeVarInt(self::OPCODE);
        $writer->writeVarBytes($this->commitment);
    }

    public static function deserialize(BinaryReader $reader): self
    {
        $commitment = $reader->readVarBytes();
        return new self($commitment);
    }
}
