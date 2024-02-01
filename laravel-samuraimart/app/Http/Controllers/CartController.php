<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //ユーザーのIDを元にこれまで追加したカートの中身を$cart変数に保存します。
        $cart = Cart::instance(Auth::user()->id)->content();

        $total = 0;
        $has_carriage_cost = false;
        $carriage_cost = 0;

        foreach ($cart as $c) {
            //数量×値段　+= で足していく
            $total += $c->qty * $c->price;
            if ($c->options->carriage) {
                $has_carriage_cost = true;
            }
        }

        if ($has_carriage_cost) {
            $total += env('CARRIAGE');
            $carriage_cost = env('CARRIAGE');
        }

        return view('carts.index', compact('cart', 'total', 'carriage_cost'));
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //add()関数を使って商品を追加している。
        Cart::instance(Auth::user()->id)->add(
            [
                'id' => $request->id,
                'name' => $request->name,
                'qty' => $request->qty,
                'price' => $request->price,
                'weight' => $request->weight,
                'options' => [
                    'image' => $request->image,
                    'carriage' => $request->carriage,
                ]
            ]
        );

        return to_route('products.show', $request->get('id'));
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

     //ショッピングカートの購入処理
    public function destroy(Request $request)
    {
        /*
        $user_shoppingcarts = DB::table('shoppingcart')->where('instance', Auth::user()->id)->get();
        //現在までのユーザーが注文したカートの数を取得
        $count = $user_shoppingcarts->count();

        $count += 1;
        //ユーザーのIDを使ってカート内の商品情報などをデータベースへと保存しています。
        Cart::instance(Auth::user()->id)->store($count);

        //DB::table('shoppingcart')では、データベース内のshoppingcartテーブルへのアクセスを行っている。その後where()を使ってユーザーのIDとカート数$countを使い、先ほど作成したカートのデータを更新しています
        DB::table('shoppingcart')->where('instance', Auth::user()->id)->where('number', null)->update(['number' => $count, 'buy_flag' => true]);

        Cart::instance(Auth::user()->id)->destroy();
        return to_route('carts.index');
        */

        //購入時にShoppingCartテーブルに新規レコードを追加しますが、レコード追加時に必要なIDの値を作るためにレコードを取得しています。
        $user_shoppingcarts = DB::table('shoppingcart')->get();
        $number = DB::table('shoppingcart')->where('instance', Auth::user()->id)->count();

        $count = $user_shoppingcarts->count();

        $count += 1;
        $number += 1;
        $cart = Cart::instance(Auth::user()->id)->content();

        $price_total= 0;
        $qty_total= 0;
        $has_carriage_cost = false;

        foreach ($cart as $c) {
            $price_total += $c->qty * $c->price;
            $qty_total += $c->qty;
            if ($c->options->carriage){
                $has_carriage_cost = true;
            }
        }

        if($has_carriage_cost) {
            $price_total += env('CARRIAGE');
        }

        Cart::instance(Auth::user()->id)->store($count);

        DB::table('shoppingcart')->where('instance', Auth::user()->id)
            ->where('number', null)
            ->update(
                [
                    'code' => substr(str_shuffle('1234567890abcdefghijklmnopqrstuvwxyz'), 0, 10),
                    'number' => $number,
                    'price_total' => $price_total,
                    'qty' => $qty_total,
                    'buy_flag' => true,
                    'updated_at' => date("Y/m/d H:i:s")
                ]
            );

            $pay_jp_secret = env('PAYJP_SECRET_KEY');
            \Payjp\Payjp::setApiKey($pay_jp_secret);

            $user = Auth::user();

            $res = \Payjp\Charge::create(
                [
                    "customer" => $user->token,
                    "amount" => $price_total,
                    "currency" => 'jpy'
                ]
            );
        
            Cart::instance(Auth::user()->id)->destroy();

            return to_route('carts.index');

    }
}
