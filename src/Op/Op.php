<?php

namespace OpenTimestamps\Op;

use OpenTimestamps\Serialization\BinaryReader;
use OpenTimestamps\Exception\SerializationException;

abstract class Op {
    abstract public function apply(string $input): string;
    abstract public function serialize(): string;
    abstract public static function fromData(BinaryReader $reader): self;

    public static function deserialize(BinaryReader $reader): self {
        $opcode = $reader->readByte(); // read the opcode
        switch ($opcode) {
            case SHA256Op::OPCODE: return SHA256Op::fromData($reader);
            case SHA1Op::OPCODE: return SHA1Op::fromData($reader);
            case RIPEMD160Op::OPCODE: return RIPEMD160Op::fromData($reader);
            case AppendOp::OPCODE: return AppendOp::fromData($reader);
            case PrependOp::OPCODE: return PrependOp::fromData($reader);
            case CalendarCommitOp::OPCODE: return CalendarCommitOp::fromData($reader);
            case BitcoinBlockHeaderOp::OPCODE: return BitcoinBlockHeaderOp::fromData($reader);
            case OpReturnOp::OPCODE: return OpReturnOp::fromData($reader);
            default:
                throw new SerializationException("Unknown opcode: $opcode");
        }
    }
}
