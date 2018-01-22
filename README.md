# BlockEditor

## About this

This is a plugin of PocketMine-MP what places and breaks blocks like WEdit.

このプラグインはWEditのように地形を編集するプラグインです。

WEditと違う点として、

- tickごとに処理をする
- TaskIDでUndoを管理する  

などがあります。

また、コマンドのオプションをLinuxのコマンドみたいに指定できるという機能も(βですが)つけました。


## Commands

|Commmand|Usage|Description|
|---|---|---|
|//pos1|//pos1 ([show\|tp])|//pos1のみだとpos1を設定します。showをつけるとpos1の座標を見ることができ、tpをつけるとpos1の座標に飛ぶことができます。
|//pos2|//pos2 ([show\|tp])|//pos1と同じ。
|//pos|//pos (1\|2)|**This is COMMING SOON.** pos1,pos2で設定されていないほうを設定します。
