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

|Usage|Description|
|---|---|
|//pos1 ([show\|tp])|//pos1のみだとpos1を設定します。showをつけるとpos1の座標を見ることができ、tpをつけるとpos1の座標に飛ぶことができます。
|//pos2 ([show\|tp])|//pos1と同じ。
|//pos (1\|2)|**This is COMMING SOON.** pos1,pos2で設定されていないほうを設定します。
|//set [Block ID] \([Options])|ブロックで範囲内を埋め尽くします。
|//cut ([Options])|範囲内のブロックをすべて空気(Air)に変えます。
|//replace [Block ID(Search)] [Block ID(Place)] ([Options])|範囲内の指定されたブロックを置き換えます。
|//undo [TaskID] \([Options])|TaskIDで指定した作業を取り消します。
|//redo [TaskID] \([Options])|TaskIDで指定した作業を再度行います。
|//stop [TaskID]|TaskIDで指定した作業を取りやめます。
|//clear|Task等を消去してメモリを開放します。