<?php

namespace OpenTimestamps\Op;

use OpenTimestamps\Exception\SerializationException;
use OpenTimestamps\Serialization\BinaryReader;

class OpFactory
{
    /** @var array<int, class-string<Op>> */
    private static array $registry = [];

    // Register all Ops once
    public static function registerAll(): void
    {
        self::register(SHA256Op::OPCODE, SHA256Op::class);
        self::register(SHA1Op::OPCODE, SHA1Op::class);
        self::register(RIPEMD160Op::OPCODE, RIPEMD160Op::class);
        self::register(AppendOp::OPCODE, AppendOp::class);
        self::register(PrependOp::OPCODE, PrependOp::class);
        self::register(CalendarCommitOp::OPCODE, CalendarCommitOp::class);
        self::register(OpReturnOp::OPCODE, OpReturnOp::class);
        self::register(BitcoinBlockHeaderOp::OPCODE, BitcoinBlockHeaderOp::class);
    }

    public static function register(int $opcode, string $className): void
    {
        self::$registry[$opcode] = $className;
    }

    /**
     * Deserialize an Op from a BinaryReader
     * Opcode must not have been read yet
     */
    public static function deserialize(BinaryReader $reader): Op
    {
        $opcode = $reader->readByte();

        if (!isset(self::$registry[$opcode])) {
            throw new SerializationException("Unknown opcode: $opcode");
        }

        $class = self::$registry[$opcode];

        /** @var Op $op */
        $op = $class::fromData($reader);

        return $op;
    }
}
