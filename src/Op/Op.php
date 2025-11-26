<?php


namespace OpenTimestamps\Ops;


use OpenTimestamps\Serialization\BinaryReader;
use OpenTimestamps\Serialization\BinaryWriter;
use OpenTimestamps\Exception\SerializationException;


abstract class Op {
    abstract public function apply(string $input): string;
    abstract public function serialize(): string;

    public static function deserialize(string $data): Op {
        $opcode = ord($data[0]);
        switch ($opcode) {
            case SHA256Op::OPCODE: return SHA256Op::fromData($data);
            case SHA1Op::OPCODE: return SHA1Op::fromData($data);
            case RIPEMD160Op::OPCODE: return RIPEMD160Op::fromData($data);
            case AppendOp::OPCODE: return AppendOp::fromData($data);
            case PrependOp::OPCODE: return PrependOp::fromData($data);
            case CalendarCommitOp::OPCODE: return CalendarCommitOp::fromData($data);
            default:
                throw new SerializationException("Unknown opcode: $opcode");
        }
    }
}