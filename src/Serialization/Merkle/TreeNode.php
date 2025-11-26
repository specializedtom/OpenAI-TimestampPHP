<?php


namespace OpenTimestamps\Serialization\Merkle;


class TreeNode {
    public string $hash;
    public ?TreeNode $left = null;
    public ?TreeNode $right = null;


    public function __construct(string $hash, ?TreeNode $left = null, ?TreeNode $right = null) {
        $this->hash = $hash;
        $this->left = $left;
        $this->right = $right;
    }
}