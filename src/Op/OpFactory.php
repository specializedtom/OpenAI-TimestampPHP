<?php

namespace OpenTimestamps\Ops;

use OpenTimestamps\Exception\SerializationException;
use OpenTimestamps\Serialization\BinaryReader;

/**
 * Factory for constructing Ops from opcode values.
 */
class OpFactory
{
    /**
     * Map of opcode → Op class.
     *
     * @var array<int, class-string<Op>>
     */
    private static array $opcodeMap = [
        SHA1Op::OPCODE            => SHA1Op::class,
        SHA256Op::OPCODE          => SHA256Op::class,
        AppendOp::OPCODE          => AppendOp::class,
        PrependOp::OPCODE         => PrependOp::class,
        CalendarCommitOp::OPCODE  => CalendarCommitOp::class,
        OpReturnOp::OPCODE        => OpReturnOp::class,
        BitcoinBlockHeaderOp::OPCODE => BitcoinBlockHeaderOp::class
        // add more ops as you implement them
    ];

    /**
     * Deserialize an operation from a BinaryReader.
     *
     * @throws SerializationException
     */
    public static function deserialize(BinaryReader $reader): Op
    {
        $opcode = $reader->readVarInt();

        if (!isset(self::$opcodeMap[$opcode])) {
            throw new SerializationException(
                sprintf("Unknown Op opcode: 0x%02x", $opcode)
            );
        }

        $class = self::$opcodeMap[$opcode];

        if (!is_subclass_of($class, Op::class)) {
            throw new SerializationException("Invalid Op class for opcode $opcode");
        }

        return $class::deserialize($reader);
    }
}
