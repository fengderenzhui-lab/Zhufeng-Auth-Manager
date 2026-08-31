<?php

/*
 * 版权动态签名守护配置（V1.30）— 由 `php artisan zf:signature:init` 自动生成。
 * 请勿手改；重新执行命令将幂等更新（已存在的公钥文件与私钥文件不会被覆盖/删除）。
 */

return array (
    'public_keys' => 
    array (
        0 => 'app/Services/.k_01.pub',
        1 => 'app/Services/sig_key_02.pub',
        2 => 'app/Http/Middleware/guard_03.ed25519',
        3 => 'app/Http/Middleware/.k_04.pub',
        4 => 'app/Http/Controllers/sig_key_05.pub',
        5 => 'app/Http/Controllers/guard_06.ed25519',
        6 => 'app/Models/.k_07.pub',
        7 => 'app/Models/sig_key_08.pub',
        8 => 'app/Console/Commands/guard_09.ed25519',
        9 => 'app/Console/Commands/.k_10.pub',
        10 => 'config/sig_key_11.pub',
        11 => 'config/guard_12.ed25519',
        12 => 'routes/.k_13.pub',
        13 => 'resources/views/layouts/sig_key_14.pub',
        14 => 'resources/views/layouts/guard_15.ed25519',
        15 => 'resources/views/layouts/partials/.k_16.pub',
        21 => 'database/migrations/.k_22.pub',
        22 => 'bootstrap/sig_key_23.pub',
        23 => 'app/Providers/guard_24.ed25519',
    ),
    'hashes' => 
    array (
        'app/Services/.k_01.pub' => '010c318ec49e7845c3a66b51c8b76306c499bcff65d0ca81c6c262ab6d3a08b5',
        'app/Services/sig_key_02.pub' => 'e0dadcf78d3d199aa65f83c6f44504c4f1bb478cd6996e03b31e42e77d045ddb',
        'app/Http/Middleware/guard_03.ed25519' => '010c318ec49e7845c3a66b51c8b76306c499bcff65d0ca81c6c262ab6d3a08b5',
        'app/Http/Middleware/.k_04.pub' => 'e0dadcf78d3d199aa65f83c6f44504c4f1bb478cd6996e03b31e42e77d045ddb',
        'app/Http/Controllers/sig_key_05.pub' => '010c318ec49e7845c3a66b51c8b76306c499bcff65d0ca81c6c262ab6d3a08b5',
        'app/Http/Controllers/guard_06.ed25519' => 'e0dadcf78d3d199aa65f83c6f44504c4f1bb478cd6996e03b31e42e77d045ddb',
        'app/Models/.k_07.pub' => '010c318ec49e7845c3a66b51c8b76306c499bcff65d0ca81c6c262ab6d3a08b5',
        'app/Models/sig_key_08.pub' => 'e0dadcf78d3d199aa65f83c6f44504c4f1bb478cd6996e03b31e42e77d045ddb',
        'app/Console/Commands/guard_09.ed25519' => '010c318ec49e7845c3a66b51c8b76306c499bcff65d0ca81c6c262ab6d3a08b5',
        'app/Console/Commands/.k_10.pub' => 'e0dadcf78d3d199aa65f83c6f44504c4f1bb478cd6996e03b31e42e77d045ddb',
        'config/sig_key_11.pub' => '010c318ec49e7845c3a66b51c8b76306c499bcff65d0ca81c6c262ab6d3a08b5',
        'config/guard_12.ed25519' => 'e0dadcf78d3d199aa65f83c6f44504c4f1bb478cd6996e03b31e42e77d045ddb',
        'routes/.k_13.pub' => '010c318ec49e7845c3a66b51c8b76306c499bcff65d0ca81c6c262ab6d3a08b5',
        'resources/views/layouts/sig_key_14.pub' => 'e0dadcf78d3d199aa65f83c6f44504c4f1bb478cd6996e03b31e42e77d045ddb',
        'resources/views/layouts/guard_15.ed25519' => '010c318ec49e7845c3a66b51c8b76306c499bcff65d0ca81c6c262ab6d3a08b5',
        'resources/views/layouts/partials/.k_16.pub' => 'e0dadcf78d3d199aa65f83c6f44504c4f1bb478cd6996e03b31e42e77d045ddb',
        'database/migrations/.k_22.pub' => 'e0dadcf78d3d199aa65f83c6f44504c4f1bb478cd6996e03b31e42e77d045ddb',
        'bootstrap/sig_key_23.pub' => '010c318ec49e7845c3a66b51c8b76306c499bcff65d0ca81c6c262ab6d3a08b5',
        'app/Providers/guard_24.ed25519' => 'e0dadcf78d3d199aa65f83c6f44504c4f1bb478cd6996e03b31e42e77d045ddb',
    ),
    'signature_pool' => 
    array (
        0 => 
        array (
            'nonce' => '9212afa5bd11f26a8df5312c0221cc9a',
            'timestamp' => '1788061736',
            'message' => '逐风工作室授权码管理平台|9212afa5bd11f26a8df5312c0221cc9a|1788061736',
            'sig1' => '331bd70b3741aa6ba5520311dee316389630301bd8b2a8a84b4c9ae2cc0bead89c942190024190f3b3bf53096a9fc8350056e49c27ad2353bf084050597d480f',
            'sig2' => 'f2885250138d9aeed66bf3aacbc1fbbfa76bc01c762e7679a6919293ef7698fcec3632b7f3b3c4e648d2631d90cbd89fed6bacd05067a4978cfcc95a02501e02',
        ),
        1 => 
        array (
            'nonce' => 'aaba7d1e678ba76c784d97099138b81c',
            'timestamp' => '1788061737',
            'message' => '逐风工作室授权码管理平台|aaba7d1e678ba76c784d97099138b81c|1788061737',
            'sig1' => 'daf31cc44a8110ac19a0d5372b84865aa74e7f87f56d57fc7b1c32f284905d80f0c3ebb39d328f3a814441145aac9c9184f88bddb258f18ad0131da7c44fc402',
            'sig2' => '76f3ee2c60284a228734f5ce317b78d2332132b3a22ae1476f74367e16687c6955c15be424abe1478fd65909ef4f880d0b04f4734b8cc76c16f6a695bd35140d',
        ),
        2 => 
        array (
            'nonce' => 'b2fcd2af614d356011666c493fe3ca0f',
            'timestamp' => '1788061738',
            'message' => '逐风工作室授权码管理平台|b2fcd2af614d356011666c493fe3ca0f|1788061738',
            'sig1' => '11b4fc155adaa11352889ee441294332ecdd30a189b89dcacb5f43472c886b175f547164658896a93c1660f83d54e23ba51648923e741db2d73466f7aa54000f',
            'sig2' => '2cad34ca401d60a2dae13c6530124a886fa6fa7e49ee6a69167ded567697081356cd4a0895de546c99c1a725c4bb445462dcec8cec7766cbcbe12eb428799105',
        ),
        3 => 
        array (
            'nonce' => '07d3368014d075eda57cf864a10fc41e',
            'timestamp' => '1788061739',
            'message' => '逐风工作室授权码管理平台|07d3368014d075eda57cf864a10fc41e|1788061739',
            'sig1' => '7478c33f5311d70da554321ecc89cbef3985a6d9e381d4e7f08565af781d8ad9648f988bf870e5b297b56185b1e9aef4a70b1c542725abc4fe4095234326ec06',
            'sig2' => '0a4688b2f475bb90da82c7594d165c730c195eaa63b27cc40ebc60a911136b5fafd4f976c31ed953f80e5dcca40a35fae6289c6147bec1a1fc7df298cbbab80c',
        ),
        4 => 
        array (
            'nonce' => '39e0ad9d4b179c3396ae42705e9f9da3',
            'timestamp' => '1788061740',
            'message' => '逐风工作室授权码管理平台|39e0ad9d4b179c3396ae42705e9f9da3|1788061740',
            'sig1' => '1a5e2984a11e4d5e7d975eee2a6652ff86faac6aefc50db26b59007206a8c5122cb3140fc67849dfff0273d20360d3daab4e14845236bf05c8b4f227fe56bd0f',
            'sig2' => '9d12cb9c8411b80996cededf1417f61267d4bfe5d0fb873573c24bc0579d85b755e3dd3f4d81ac086afec722ac31825172c6739961be350803b489d82b26b90d',
        ),
        5 => 
        array (
            'nonce' => '16bb1c3c511991afb3e9a036a56f9f2e',
            'timestamp' => '1788061741',
            'message' => '逐风工作室授权码管理平台|16bb1c3c511991afb3e9a036a56f9f2e|1788061741',
            'sig1' => '83e0745220c7b1e5590707842c5f113a83696fceff138f1c373f90b55d8f48d3af04dc3cba0dfe9282e13feb0c93f32f5672dcc484090ea6e9417c6a054e1605',
            'sig2' => 'c80e35a586214b076d6db5d3a657594b21270bbae678706e761b93fa1a2ed1a4a9bb2bb10e80f065ae1f85adb207c441196169a1db405e8b30733325ddb45b04',
        ),
        6 => 
        array (
            'nonce' => '91cf0324ff61b0df2a09ef7746333d9f',
            'timestamp' => '1788061742',
            'message' => '逐风工作室授权码管理平台|91cf0324ff61b0df2a09ef7746333d9f|1788061742',
            'sig1' => '7f17ad6f63da43cdc661b56bffd1c6cf825ccc899ce9e5082b91fe0f04c52a97b96f9d203e342d9094bc57eef54a4963f9d3d2c57a3bb1f13ead6309a8750601',
            'sig2' => '2f7862768d11376fa33b2e5f7d62ef2d2026b9f03d5fbb1d3c024e8cfecd16cbbdb0dcc756cc54bca8a377c97f4ef1cc16b2233130c4d733b1a61c29f492480d',
        ),
        7 => 
        array (
            'nonce' => '2a8cde9665b33906d0b4b6e4651397a9',
            'timestamp' => '1788061743',
            'message' => '逐风工作室授权码管理平台|2a8cde9665b33906d0b4b6e4651397a9|1788061743',
            'sig1' => 'c71d5cc5ee87a41b483e6a9f0c9b032cd295367c5c40de5d4185e4bcdb7dafdde51fed700c5d2e2073c6430289e675ec349e1bb365530d71b735b107f37a760f',
            'sig2' => '918dc79e3d0a0d159b0c5b6bb38018fae8a603ee6e3ac0a91197f73d7b6923b46e254ee80aa1479adde437b71625cf31a8240a2e78c23f8733630888de606d0b',
        ),
        8 => 
        array (
            'nonce' => 'abc9e7c5acd1244abe5ddac1adb4ed22',
            'timestamp' => '1788061744',
            'message' => '逐风工作室授权码管理平台|abc9e7c5acd1244abe5ddac1adb4ed22|1788061744',
            'sig1' => 'cac131775b68402930b95750591df78ecd612c436521fd2d1b7d7716cc05e686ac02e39f77dc094792c47ccb8f4025453382044dca37ef948be70bde4d301304',
            'sig2' => 'c1c8b2aa5d8ae62b04b7f1c1ce5b05b0eeceada061adb7d0f9acfc34259a7e303215d38dbcbc63b5cf871fafdf3023d879a3e0621a6bfb02d5389938e32add06',
        ),
        9 => 
        array (
            'nonce' => '3818564b2e2e33bf4cbc124979bc1cd8',
            'timestamp' => '1788061745',
            'message' => '逐风工作室授权码管理平台|3818564b2e2e33bf4cbc124979bc1cd8|1788061745',
            'sig1' => '545598aa6bcd46c44acf90d78a901b3148b211d990b6b36e9d0aeba39c3f338191f046fcc1163119054a5741ce659cfd9e3419b5b129cfd11c2d7bc945ea7b00',
            'sig2' => '85d02a593a50d1a01810bc02878bceea79e99e2dd4d20926146fcdc1d5f0560307c79e22e5f89ae6d704b5b1948462ba5ecc9932c31a6c75571996b68686d209',
        ),
        10 => 
        array (
            'nonce' => '19c583a114a6ac1f6ad46cb9e1c127d3',
            'timestamp' => '1788061746',
            'message' => '逐风工作室授权码管理平台|19c583a114a6ac1f6ad46cb9e1c127d3|1788061746',
            'sig1' => '0269a18b4d5da0f147166ca6fcba29aff6f22d705fe4bbf5291088e9e61a7eb8db7e2c75436b1c18add315e5127bc083020f83b2d89205dbec43401872a3040c',
            'sig2' => '9904313f109c050b3f5aae41f1f4c7a94ec8a88fbd4a8fedfb5d26aa2838f0b09ff5c613c26ebba26267f0d508ea866b825c51242b9a9f2d9402737758149007',
        ),
        11 => 
        array (
            'nonce' => 'd5e1f3127c16eb6a5ca7e46b5183f2d5',
            'timestamp' => '1788061747',
            'message' => '逐风工作室授权码管理平台|d5e1f3127c16eb6a5ca7e46b5183f2d5|1788061747',
            'sig1' => '314242708f075f2f6973f1c001ea32814526540947d90c054773f1d4d7a4e98b716e8103032231e0805c0f0729a5b817f18be7c45286ede0531ccc69cf5f9704',
            'sig2' => 'd47a9bc3183237c7618728f3a581150e72261e1cabc9ecfee2779ceb3f6dd3f31828d748f6fd23f677e45842a20a5fafe91f07b06fc075763f2f3f1c4f50ab00',
        ),
        12 => 
        array (
            'nonce' => '7cfd0d3d484c7c8beebd87acfbe57a77',
            'timestamp' => '1788061748',
            'message' => '逐风工作室授权码管理平台|7cfd0d3d484c7c8beebd87acfbe57a77|1788061748',
            'sig1' => '04203cee8ba4c341916959366be865ec5595e96bf664d235f6d9cf840652425865bfc8221eab82b8a8d686fb3da4e41d28a8bb47dae2dc765d8c3f17fc9a5e02',
            'sig2' => 'f60c1f1c480d77f4579182fe1af593bd249d4e627f8f2f02a5c7156d78bdcd7374cb70e018330150cd0c27c437838e63fbd365685611eb36ddc358deb2bba502',
        ),
        13 => 
        array (
            'nonce' => 'd6fcc06940392de3cbf6704e486b30f2',
            'timestamp' => '1788061749',
            'message' => '逐风工作室授权码管理平台|d6fcc06940392de3cbf6704e486b30f2|1788061749',
            'sig1' => 'bed836836de8711352a5060f48d63bbf9fb4541d2931a3ec73f4de461f1becf2b9c2f9ed4167985606aeec947ccec2583857f84809b346b3bb1e97e51257f00c',
            'sig2' => '9861984c592b88a1cc51b4c002b06218153f613c3ad5c286131b029a271ac7324722cc2bf537c5e2df11885f4cd84b1d7b6ec604a61e189a29ba1e9082e6f10d',
        ),
        14 => 
        array (
            'nonce' => '738e9e57f07926e5b45ffa491eb3ac2e',
            'timestamp' => '1788061750',
            'message' => '逐风工作室授权码管理平台|738e9e57f07926e5b45ffa491eb3ac2e|1788061750',
            'sig1' => '48e773268fb752700872c586589d963302a6df99084c02ac760609bbcc73e3e9af023134dce4c52e29bfa4c7bb88cc5945de2f15c84fd42c14d2e82a4956d60d',
            'sig2' => '21773c3aef463feaff50a11759989afe48f7ab3376dabc199abe7da2136ab77d74402ff576744944c6a162b08d06eb686cd44710323403709fcdb38759966702',
        ),
        15 => 
        array (
            'nonce' => '79203ee8c09aade0d9faa62b69de4dcd',
            'timestamp' => '1788061751',
            'message' => '逐风工作室授权码管理平台|79203ee8c09aade0d9faa62b69de4dcd|1788061751',
            'sig1' => 'db647411d7ab06bef1b0b7273fe4b9ff1a5e2d08f9ea1d713913fad6c10f5e3ec3fadffa058d991abd8ec4e1ab252b1f428f3dc72286c1a8fb02e5ed94f40a04',
            'sig2' => 'f09c481a50ba9184883081f3fe34d39b71b32569cb626affe4769f14f63d05a9b0b78ec096a4ff764132377640bf9e3a58a1401dd6259c88ebb2127d236c3501',
        ),
        16 => 
        array (
            'nonce' => '5e86475e7b93958254a0de24a65a963d',
            'timestamp' => '1788061752',
            'message' => '逐风工作室授权码管理平台|5e86475e7b93958254a0de24a65a963d|1788061752',
            'sig1' => '7dcf53c3905f3a183af7ba7e80cd3c972910196d9bb05ed2ede46a757bc2dfb9f64100e8ba7aa97943abffae051e3f41bcc1d4403e024f66161aee8578d2940c',
            'sig2' => 'e06bebcf48e1680f3794f236f80e538c703cd571bb5dd5b84cbb892a58872472120b631faa4a3db3f3a52252e45300251dfc0d5527168ae8c3463b12862b460a',
        ),
        17 => 
        array (
            'nonce' => '0c1e591d1efb6ed35ab02a1e3b113461',
            'timestamp' => '1788061753',
            'message' => '逐风工作室授权码管理平台|0c1e591d1efb6ed35ab02a1e3b113461|1788061753',
            'sig1' => 'b1fd3001a0c248a450e3f23a6ee6185ca964869d7aa01efc95f1989010cadb743bf38d56b5a7ad7e044c7a36da2a53180f56a6bc93eb7840381afef97d52330f',
            'sig2' => '62701a7b1b3900dd4b89fcc699c0574a9946ecebc5048b3f4ecce9c9edc99174c5d66c3b74b23bcdee6d2e3d29b2717447df6a0899a2a420c3550c7027f8f906',
        ),
        18 => 
        array (
            'nonce' => '31d197be8d4bddb2c3141fbb2bbad964',
            'timestamp' => '1788061754',
            'message' => '逐风工作室授权码管理平台|31d197be8d4bddb2c3141fbb2bbad964|1788061754',
            'sig1' => 'd429639ffbac9b74eb1554e7ae5cd9c71f326bf0c61f0295c8adebb8f36dfe02ddcad668af32af88bba93c798ee5d4a5339d2d53a989e930b89660f165ecb708',
            'sig2' => '9088590256e48483a984ec33164eb565c6447361538ff2c19aafc3f352cb732856036f9b256d6a5aa32d6419e0cdf8df50e1702b16a752e0c6a3adbc3334e201',
        ),
        19 => 
        array (
            'nonce' => '16ebdb9a5d32a558825038bb424d91e5',
            'timestamp' => '1788061755',
            'message' => '逐风工作室授权码管理平台|16ebdb9a5d32a558825038bb424d91e5|1788061755',
            'sig1' => '63da1601e2ccac3af38ebb9b89e531620b074544513677812ec7087b520b0b11d271d1d377b2944bc5e2311e606ed78c69e0fa6dd7b0617ec90fa7afa928fb03',
            'sig2' => 'a4983ca27b3b53e303a7a6f2f32cf46b9b3e3e71a3513c5df431e39ad83f99998b9c9535e806e41f3569a1be72422d394ff9494677bed84e578cd600667cd500',
        ),
        20 => 
        array (
            'nonce' => 'fdeac3e836f7aea60e6474d81d0d7a05',
            'timestamp' => '1788061756',
            'message' => '逐风工作室授权码管理平台|fdeac3e836f7aea60e6474d81d0d7a05|1788061756',
            'sig1' => '57005c69d66a7e64e06eb54001883c6da15f773ab005a87b18ac4df7aaef58b2365bbebb16a198b5fa36b42140ee49a705b7b68d642d5986a81cbd37b93e8c00',
            'sig2' => '2c12bca199a788d40e022891f2e3976af5dc818f8bace9fcd65811f3bb65e5a8c087a1834a1fc078520bab1cb7cc86bc8f0f33f4ea7f5db2b722ab13aaab4c05',
        ),
        21 => 
        array (
            'nonce' => '9a52b4ef295f3bef20d5e28f0955a152',
            'timestamp' => '1788061757',
            'message' => '逐风工作室授权码管理平台|9a52b4ef295f3bef20d5e28f0955a152|1788061757',
            'sig1' => 'be21189ea8daf570da06f54ab392dca8692c6e896605a432554dc4f7213808f0243b932dd598e6c4f1c6a12ad31ff5195ca7075a4692d56d56d3bb44a3f1d30e',
            'sig2' => '408fcab76525495a7c189ce131dad36e30303e430a7dd3bc7ec15c0088c72f621c6f010ffcacd71c7d1d72501765c9355688d56a88aafd0dab2263e30fb1430a',
        ),
        22 => 
        array (
            'nonce' => '8c83890e3570a0f4ed32c30ccdbcacc8',
            'timestamp' => '1788061758',
            'message' => '逐风工作室授权码管理平台|8c83890e3570a0f4ed32c30ccdbcacc8|1788061758',
            'sig1' => '4f04fc15d4b48f36b2fba312caa274389548019b3c566074f2e263280d41956b0f9c291739c4185bb4a6bb6f5f991ec9cce1061b7fbcbda3f8dd9bb73a1c1a08',
            'sig2' => 'c3bee0546f80735a3800b8e2c0e73338cfd9f1cc78d828d8383bdbf1b763149146253bb2104e2f22ea4165cc0858df0fca1ba1fb8a639aad237e46eb1872e80d',
        ),
        23 => 
        array (
            'nonce' => '9362504c571ea666683062124b426af4',
            'timestamp' => '1788061759',
            'message' => '逐风工作室授权码管理平台|9362504c571ea666683062124b426af4|1788061759',
            'sig1' => 'e1e4aa3783f7c9b7a18be79c2f8004b6f7b8ced92c703b31b6e05055f6d13698f3f1fdb0685de6a2eb54c9eba27c4d6e25636fa51be161e2d34b4c3f9a50090b',
            'sig2' => '7beb1f18e208871ab3422553ebbd560aaedfc69a65169643841ba19aa62d9b89cacfc8252946431dd874bf6265349c96d3dace69ea1132b1c68e7a43d7603304',
        ),
        24 => 
        array (
            'nonce' => '4e5237462f7df75d5db38670e7645633',
            'timestamp' => '1788061760',
            'message' => '逐风工作室授权码管理平台|4e5237462f7df75d5db38670e7645633|1788061760',
            'sig1' => 'c3c724d278e4a5d0a20c1486a1f7f94e05eadef914781e75edc2b05e62d86e23d37e19e242dfd4a5b09cd66b321bf4bb01c5a4df084a705f79cd266f4e6ed80f',
            'sig2' => '872082fdf388bf8dca546dcdefe94a3f092ebe175e49a62a6561636fc55117326eb83aca46f43aaddd5c8d7ce7cabd6d2601349795c087d320e9f587dd756902',
        ),
        25 => 
        array (
            'nonce' => '2b419fe4627c0dde4e032962c9ac8e93',
            'timestamp' => '1788061761',
            'message' => '逐风工作室授权码管理平台|2b419fe4627c0dde4e032962c9ac8e93|1788061761',
            'sig1' => '4ed558ba6986ec6be002dae43d07e7d1567c287910094abd2ec97126808d94de4b4a6318afcca8ae57e45c07be590ed5b098e9c7b5713e890e206fd822280a04',
            'sig2' => 'f1baeafa3e5080b632ea9976b21e711e77e6ed9febbbffd1f0de4e030f01f923a9d18f90f457a5659809422b0bcfc9b1ee5cd4b3d61422751769ae0cbd777d03',
        ),
        26 => 
        array (
            'nonce' => 'e4aa25a7a54bb24e98eea5584048aab3',
            'timestamp' => '1788061762',
            'message' => '逐风工作室授权码管理平台|e4aa25a7a54bb24e98eea5584048aab3|1788061762',
            'sig1' => '74c66c56ba120060cfcd582ec02c6dd0a3b788d1c544b5cdefaac8fb3807a8ba8d3b1fe8b4894f21687b9ed9565c5ecc5d85075eca8d82d5f4e3c6619e98650b',
            'sig2' => 'c98e1db8a79c2f1b8d3775ea9e76917a1bcbd8d2d8cb1288840b1bfc5cf65e8f5f2e6de26235211680ca7b88ed78fb3ab1eb7cea49a7987baa0b586f3bfee501',
        ),
        27 => 
        array (
            'nonce' => '2799e0becf087c529bbf3b5f3cc29c00',
            'timestamp' => '1788061763',
            'message' => '逐风工作室授权码管理平台|2799e0becf087c529bbf3b5f3cc29c00|1788061763',
            'sig1' => 'c5f4f2cbaccd0d0fade12a8cc7ec74cdb9e20c84d383d1dea3a74d029bbfbaa8496135434e2a6f403638c07a05404792c4ae146786ad9b4f4637de4a906ba50a',
            'sig2' => '58d4fd476a9441a94297d8c8cad61c1006b6c8a4ab73299c9cf3614240b673b30755a698345b867e2dc9e1a716489c7b94734bb7c8f3f99eff6f6d97ba48990d',
        ),
        28 => 
        array (
            'nonce' => 'b8159b6cf68d3a615613da077dfb1c4e',
            'timestamp' => '1788061764',
            'message' => '逐风工作室授权码管理平台|b8159b6cf68d3a615613da077dfb1c4e|1788061764',
            'sig1' => 'd8110a23584914c7068144afe36a80becb6abd6aaef7716ac299222dfc3941792dcc9b428252651189d5c7405adca5dc29722ba3580c22efdcc5f6b4226afd0c',
            'sig2' => '8ac1480692fa206115a874bdf92ca5b70a2cfc3e78a942f99ef51b91ad26bf15036e699ede7e6a47083e75fb573db4c6ed5649f2bf3316de87b84cca825d200a',
        ),
        29 => 
        array (
            'nonce' => '6686795d7dcd28cc5a556ba825bf22dc',
            'timestamp' => '1788061765',
            'message' => '逐风工作室授权码管理平台|6686795d7dcd28cc5a556ba825bf22dc|1788061765',
            'sig1' => '9a62d08d11864da2e2f22850d3c6b749b5b954a4407e0e28b5f01572c07d3d0d5d767dc953adaa976d57ac54ba6a83e1b66c64c00f9ae9360a38afaa82d7850d',
            'sig2' => 'ba215c611dd38ffa115a3d6fdc10d3bd02745744c9420dd2f481689ecc36f2072b62e951e716296692cd3b889628c73dd12e29a35b1dbff79e9982038bfa830e',
        ),
        30 => 
        array (
            'nonce' => '0daf3158220db3981611305e0376b7ac',
            'timestamp' => '1788061766',
            'message' => '逐风工作室授权码管理平台|0daf3158220db3981611305e0376b7ac|1788061766',
            'sig1' => '68bded3a08d60ff3c33802dc2dc5458aea2cf66824df7a425bf12f4e599bded7d4566d667477daae5ebaff811fa04b9e95d1b15ebd146af1b13c8a63ade1840f',
            'sig2' => 'dcef7f206283e4fb93b714560b886493176a3527a139179ad7f2d60eddcb05c3ab7175862b6b99dc943ebbce8d1155a15c1076a89e2bf23ec4f6d3fef4bcab09',
        ),
        31 => 
        array (
            'nonce' => '10aa7526e9989294692657c1838996b4',
            'timestamp' => '1788061767',
            'message' => '逐风工作室授权码管理平台|10aa7526e9989294692657c1838996b4|1788061767',
            'sig1' => '7d5cf734fbe2d927879e8159e5966043ee3f0fe0fce9412f51dcf048f7155f94704ff1644f48abdfab9477454fe8c07828e0ff4c8b46ebcab5c4e0e60814d802',
            'sig2' => '4b6bc54a835cd2f36369f70c14828ca1edb74069453025b30a9d9b4f052391551a3791330784e83e6603424e5304da28385333f6a9b9b312bb1d1fb6a154040f',
        ),
        32 => 
        array (
            'nonce' => '441b91981d6d06cf640a46836d5c0324',
            'timestamp' => '1788061768',
            'message' => '逐风工作室授权码管理平台|441b91981d6d06cf640a46836d5c0324|1788061768',
            'sig1' => 'f2d9aaa6ed62856f0819cca8511a00be8c968ca6ed6e4431e7f0687b76c27ad73de34e7481d9019118ca61ca4c1e02e400ea1e227b55fc873f8dc90da52db306',
            'sig2' => 'f813dd70286ca571b96d5c00b502dd9e15a5619630964fff02d142311e3c5bd27d4f9d6a445da0ea9c657e62eb95cde2870fbbce1b05f160e6f45aac239a1408',
        ),
        33 => 
        array (
            'nonce' => 'b7e2f6443bd6e6923e2b2c648627dc04',
            'timestamp' => '1788061769',
            'message' => '逐风工作室授权码管理平台|b7e2f6443bd6e6923e2b2c648627dc04|1788061769',
            'sig1' => 'ff20915c7b3b897ff40f3b846bfc40d512048c283e97f298cf1842607835002824a92ec20c95b7950711dc2a0baae764526d8823ef15a3b66047baf04e016e0b',
            'sig2' => 'fe104cbe56c2fce0a69ed43d10beca3bb8f3b89bf07329fbc0249d6fc69e096c2cf21f10ea6d0385c85b877095c9fa03c698a0ad5797f18bcb513560243ea609',
        ),
        34 => 
        array (
            'nonce' => '9344a14ba9aa148b9ce152bf9c5d808c',
            'timestamp' => '1788061770',
            'message' => '逐风工作室授权码管理平台|9344a14ba9aa148b9ce152bf9c5d808c|1788061770',
            'sig1' => 'ade48d0a89bc67f9505aa3e489806b3f7aa0c8a45f39922478b58429cceb5c73edffd1b7a9d0e28b2423e05f22da7bb2b7b1a22f13cf941d2a82bce75b6a6109',
            'sig2' => '223d2ebc82da75dda16a5e47a02f1c95cd5b501358f21c0e26ae7873c00a59718735c05ec813badf33882728d4fc98f6e35cd044f3f4b915431133b083fe3203',
        ),
        35 => 
        array (
            'nonce' => 'd290d66c162f42304ceddfaed51e6f45',
            'timestamp' => '1788061771',
            'message' => '逐风工作室授权码管理平台|d290d66c162f42304ceddfaed51e6f45|1788061771',
            'sig1' => '9103ee870a4206640c5411ff74ea1cf950bfb06697592f7909dae96ee1dbbb8eaa359b9b3b7a506ecbe1df781361b386689d5e1ab215e047a3b83ad309f9b903',
            'sig2' => '1430c7f4d124368443bc3c6a090c8aa3fdb4b499d51c686e8b60aa0da330e925bc80a5bed24b54a8bf403efba5efc4975e58bb6875466ba193ccd8dc60d08205',
        ),
        36 => 
        array (
            'nonce' => 'a6744fbf315b468ffdb5b36682f47bcb',
            'timestamp' => '1788061772',
            'message' => '逐风工作室授权码管理平台|a6744fbf315b468ffdb5b36682f47bcb|1788061772',
            'sig1' => 'e42033d7945fa166058987a59696e80f683df144c51cfad31140cbf38ad37e0efe57229ed997794387f06eed04711b1b3bb246099ad5a1eaa22a2420b8ce6307',
            'sig2' => 'd09be07c4069bb3994bd90d470f11a24c9c7b142434692b1a7bb9e13c6b1f35a67d320f8ae3331d1ed7a2fe51e0cea9ef98529912dc210a7b6c2debbffb0ee0a',
        ),
        37 => 
        array (
            'nonce' => 'b69caf703cd386d9ede0ecdea914aff1',
            'timestamp' => '1788061773',
            'message' => '逐风工作室授权码管理平台|b69caf703cd386d9ede0ecdea914aff1|1788061773',
            'sig1' => 'a683d6974fad605dbbc157a0144311d1d62e08081e839b1437d4eb22883e2187a3d9ccab6caf54452b129b48670ea9ea9d728093046936d4c32bdee22a83ad05',
            'sig2' => '04999cc8c828b814b125cb53f874c9d2c32cff91c802421994bf9038a66531b00f3e541cb22506b057cf7857bb3ed693177d39ca289545eb5c72c99cbd96d201',
        ),
        38 => 
        array (
            'nonce' => '238012951596ab692c7baab463070665',
            'timestamp' => '1788061774',
            'message' => '逐风工作室授权码管理平台|238012951596ab692c7baab463070665|1788061774',
            'sig1' => 'ae35161eb39b2552d0effe30676414e23ac1bfd0aa653f9e045684787f17d1dd4c6b5bd9379456b7625e87fa8737319bb59f0a8dba6282e6f849a368e0823a06',
            'sig2' => '29335bc61f5b353e086f5933a788fee01395dbf4b6c5ecba3fc18c0c7e099f22d25a5e3ab8597de03819ad056d48ca5e61fd4490dde6cbaed6cdfe5903153c00',
        ),
        39 => 
        array (
            'nonce' => 'fd8bcd9554d6e9972fb1cff594413f85',
            'timestamp' => '1788061775',
            'message' => '逐风工作室授权码管理平台|fd8bcd9554d6e9972fb1cff594413f85|1788061775',
            'sig1' => 'a03429900f99dcf4774de4f4187187d7b05ca7b78f9cecbff98c0f4b25c8657121085b785d36ebde658222623d99a6ba9daecddf93f114ab0208a270497ac50a',
            'sig2' => 'a3191ff588b1a75f65b1bf162914a86d566fa604519c1ac9a9d8c65daa5e59a85ee5ce1b2435fb6d4fafcc68def4af6006f62e85bd787301a212f2ccaf0fdc01',
        ),
        40 => 
        array (
            'nonce' => 'd01e77b0fa347ddcfad6ab03ae90fde0',
            'timestamp' => '1788061776',
            'message' => '逐风工作室授权码管理平台|d01e77b0fa347ddcfad6ab03ae90fde0|1788061776',
            'sig1' => 'd6b5394c607194f7ef1b8f0438a365171143ad03b0488456c39ac1750746032eb58cb556cb516f9aa8154e3ac07e983079ea46a6447704d89e3e1b603d08c100',
            'sig2' => '1749673695a7f7dc8c156abd615334aad5f5cfb974f514756ce6372cedee98409c20d43824bc9488c742a081d95963f6954034d19a7dbba145a2523fdb018701',
        ),
        41 => 
        array (
            'nonce' => 'b8957d5238d0fd7f2603bd3b08b1ea17',
            'timestamp' => '1788061777',
            'message' => '逐风工作室授权码管理平台|b8957d5238d0fd7f2603bd3b08b1ea17|1788061777',
            'sig1' => '99d52c2105a32b1d29e928d5ff3c68ce8624d37f7edfc6dcb9a251d7f4b1b7b38cfc3f5b7f8af821831fea28b50ff58ee27fae8a24351263d8937bf6db89ef06',
            'sig2' => '780fb7d2a2fb5e65d2fa71702c9b41f628baee9e4af919d198b600deb1c66ef085182496774dfffbd281fe57feac59ec85ab9a076b2ac32892066831ef310c04',
        ),
        42 => 
        array (
            'nonce' => '68569dcc7bbb10eba362af3c9c3a0dfc',
            'timestamp' => '1788061778',
            'message' => '逐风工作室授权码管理平台|68569dcc7bbb10eba362af3c9c3a0dfc|1788061778',
            'sig1' => '49519e11891599ea3380f46f38d2cf1b6a68221a9dd9bda649c914208c3c1407868ff4c5f118ea0d55628356746489e2ef1bea2f0dffb750263c7625416fe00f',
            'sig2' => 'a83c19c2f64e119aa05e38b8a27bdd678f3e7c84141e5d12ca115196b6a780755525494a32539bbb5d5656d8ab401ef7abd07963b299d890446b57180f408609',
        ),
        43 => 
        array (
            'nonce' => '8d77eccfde00e40305a93d060e719908',
            'timestamp' => '1788061779',
            'message' => '逐风工作室授权码管理平台|8d77eccfde00e40305a93d060e719908|1788061779',
            'sig1' => 'a49f5df09c0f93500b6cf3c6a2d7aa7998a9563ff5d48029039bd891dc42ee48fdd9f09e8b327631ab38063270779856c3a33a68b5ffb342325946de4d97c306',
            'sig2' => '4a1241941ea5dfceb1712f9f8f628d460876f5a906a4afbaa446b09e55b5c4187a7ac562ef1d37d0c8afbff79a3066e4a44ef41d94ec36bdf514f0e07f166c04',
        ),
        44 => 
        array (
            'nonce' => '178ece74b2335067c9b11bb37807da77',
            'timestamp' => '1788061780',
            'message' => '逐风工作室授权码管理平台|178ece74b2335067c9b11bb37807da77|1788061780',
            'sig1' => '3add2f7eb226fd7dcc763d1ebd4ae791edade308356cc8ae7ee2a27a766a7df6d33c4a91eb53cb19d2c73a1aae7b53cb4fdbebe8905a5a263df421cd91ae2007',
            'sig2' => '163569f1c0b175511e195909f99638be6e885c141d82471ce05f7a8b1ebd5d8610aa4fccf13511c94110b87fcc421df922f12f792813ee3292af7a46a35ac503',
        ),
        45 => 
        array (
            'nonce' => 'ffcd8a37ba3f7f5c458e9c01255f85ad',
            'timestamp' => '1788061781',
            'message' => '逐风工作室授权码管理平台|ffcd8a37ba3f7f5c458e9c01255f85ad|1788061781',
            'sig1' => '2bd296a0e4fb26bac3bc03c2078b78d90ef585bf14cd97d21691d752455169b54db0fd3dcd808a484704b408747bb34541232483743cc01ee76588e8433ee400',
            'sig2' => '17964ce9a53aee194d0cf6003c5e6c46f896d92b0b50855f1f3bb06e1836d68b24cdd7064379fd309f464f545d307face96af9789af4f64afbbdfe769b753206',
        ),
        46 => 
        array (
            'nonce' => '6b39568ec3c5264fc09af5cefd7e8731',
            'timestamp' => '1788061782',
            'message' => '逐风工作室授权码管理平台|6b39568ec3c5264fc09af5cefd7e8731|1788061782',
            'sig1' => '176ea76ff9471ee271a53ec52c6dfebb980478376241eb59c7ba2643695093212fa2708a88cb050dbf0040f2b3a847f7444fd70ec64eb0dc0ea069f6dfeaed08',
            'sig2' => '7c56fc8ea4077683b88cf3696b6ccf12cf9a86ee9992f1253cad8d942f0a5a3f10a706c0d79ddb8f64e2dbe3af78b636e71fec51ad4993972d573e451e4c8409',
        ),
        47 => 
        array (
            'nonce' => '4ee38df1c8e71f1fb9607b58a91477e8',
            'timestamp' => '1788061783',
            'message' => '逐风工作室授权码管理平台|4ee38df1c8e71f1fb9607b58a91477e8|1788061783',
            'sig1' => 'a884b805b14a53b00f1f03ee7fbd8f975fe79e5c327e131a8f0d73426f73656e97f282ae162a135b70b8be7a37a8129f5c27c961e8f087aa97733e350424530f',
            'sig2' => 'af56af2ec55e374ebd1d017a13c9e46a3a19bd1929a4b3752bf44f85fd60cabf029cf235f92cb69d490f9718b9adb55054c5f9eaea825cfb89302fb8dd931c08',
        ),
        48 => 
        array (
            'nonce' => 'e5d253cd237a8d79b1ca9690f4cc456e',
            'timestamp' => '1788061784',
            'message' => '逐风工作室授权码管理平台|e5d253cd237a8d79b1ca9690f4cc456e|1788061784',
            'sig1' => '06db15e9ddc4b5eb48d1ebc9a221547c876480f272c6e22d9656c22f3be048a947a6f9dbe4ed7c4ce4d4da0a95cc71d8ccb97fd70cda4bc2c70f0cf47424a900',
            'sig2' => 'dc106fc5f41b9d61191be8eab5650b488227dc27d49332dbf27e31f0c04771e1a349618641af783175ef37766b6fd144786a08af9c65528608fbb473138c0701',
        ),
        49 => 
        array (
            'nonce' => 'ee31ff6878bbc658457df25f4ca237f8',
            'timestamp' => '1788061785',
            'message' => '逐风工作室授权码管理平台|ee31ff6878bbc658457df25f4ca237f8|1788061785',
            'sig1' => '89f4c6dbb449f66f6467939edf0dee24c6d0f90be50689cbc97afd4c0a1392af2ff2725c591f3f2f6bb6c098a84a93e7b8daa0d5adea1dfba000a4eaf52ede03',
            'sig2' => '35b69206ad712302b0aa38af2b4f86e11dc6d34af01a70ebfadfc77f66b8fbf870cd7a302a360327a877f112564a872ae90fb6a4a5a3a45a6bbbbdb0a815f906',
        ),
        50 => 
        array (
            'nonce' => '4ffefd3ebd19af3d7f43dfad839f2e9a',
            'timestamp' => '1788061786',
            'message' => '逐风工作室授权码管理平台|4ffefd3ebd19af3d7f43dfad839f2e9a|1788061786',
            'sig1' => 'ec5f18721274e97535f324d48d9d5a1e3f32097e17f7f20d684a663b5b85e5f370201c3a7d85b0d18d7d4e9756d1cddca151c3b4e48012201e21436bb798fa09',
            'sig2' => 'ff93aec97c04a85a46e94e06b2452a618798d53c7e3537bcbaef9ad95d547dffb0d7f69bb81a9154fee80dfe3c6811b2bb1f5a12948c57a528edf52b3c708a03',
        ),
        51 => 
        array (
            'nonce' => '2bed05e216c6733a025affb335776859',
            'timestamp' => '1788061787',
            'message' => '逐风工作室授权码管理平台|2bed05e216c6733a025affb335776859|1788061787',
            'sig1' => '34b8abf5e6a7d42ecd8fe91f9cb25ef0d828fed252bab76e8aaba6c25cd6ad432365d5978c09b730d47caef214faaa8cf058bcbcff18f2d2d5d8881086ab890b',
            'sig2' => '03eb86bbd3fe9fa8aaec02f8d5d9ff3511f53d78e54ec717c0242a8bc7249e11c791312326e339fd522836120c305451fb46872fd4de67f1131ad902354b890a',
        ),
        52 => 
        array (
            'nonce' => '9a4c43788d6f6f55f08ac2b2b724b0a7',
            'timestamp' => '1788061788',
            'message' => '逐风工作室授权码管理平台|9a4c43788d6f6f55f08ac2b2b724b0a7|1788061788',
            'sig1' => '26b32641f6778b67e003443884e5187eb8ce12467eb69aee170e079aa2fd01e45fbd3bee7c138766e591b56693a82ec369042d01e0df2398efb075ee14a66e0a',
            'sig2' => 'e1440bc072a459014a78c90a98cdc24163b877bc35db9f4308c268516ba4abe5a61aa4e23e1397c3ce1e430e044527e5ab76dee1655f057e29413c22957b5505',
        ),
        53 => 
        array (
            'nonce' => '3aeab1ae7c9dcdce00b79381729794fd',
            'timestamp' => '1788061789',
            'message' => '逐风工作室授权码管理平台|3aeab1ae7c9dcdce00b79381729794fd|1788061789',
            'sig1' => '3bac9f4f120396c0e56de7d60e664cd5814d8a81f4808d671aa90787f4e8220cd5280c78cd4beb82eaf1dfc078888c36814d0a218f4eefcc148803388432cb0f',
            'sig2' => '8834ee6254b5027816429d3b055c9d2de7b834e36e0f93a9cccb84132dcfbf1a77fc06b14e6b0664028c2635b54b1f2e5e8723b189198c38e8ae3f841b055f01',
        ),
        54 => 
        array (
            'nonce' => 'c63d2682ae023fbdd3aadb083159befb',
            'timestamp' => '1788061790',
            'message' => '逐风工作室授权码管理平台|c63d2682ae023fbdd3aadb083159befb|1788061790',
            'sig1' => '6f14bd149e9f894fa88cfe3dd8e96ddbc6deb657fa2bccaed6968207824f9fd473168618078a9f6b90ac8900b799e0f0090c3aba1e2562b1e17918673f9de80d',
            'sig2' => '041e8883dd9eed0ee95bf4bc33299171a586b2041e644954924044100581afe384b173f9e729da7189e5200bb69cfb61433eaa2e9e39f5c89f399ff178192304',
        ),
        55 => 
        array (
            'nonce' => '416be3b7f36646d719e7e9092a0a53d8',
            'timestamp' => '1788061791',
            'message' => '逐风工作室授权码管理平台|416be3b7f36646d719e7e9092a0a53d8|1788061791',
            'sig1' => '22c5bcdccee1c95f4af31e82b7b008c32509609a0e5bc1992e0d68a30a08b3f116887deda491c902fe4e69774d2784e565501d8d88fe7dd7d84940ef9604210c',
            'sig2' => '971c0e4485462b96cfe4be7b5e94aec16f1263e11202354a0ce1e0711a3d6ed3f65c6101d7ef758be873f709932c118de008e46d37bf40c02cd3c68a0d93140c',
        ),
        56 => 
        array (
            'nonce' => 'bb7673c97eb7fcca9a8bb874897ca5b5',
            'timestamp' => '1788061792',
            'message' => '逐风工作室授权码管理平台|bb7673c97eb7fcca9a8bb874897ca5b5|1788061792',
            'sig1' => '2617591f636b203bb338120685475d6c651eb8ef0f51b6e62e20eec7ff2ed7e05e1be9ff71ce6ada46c2299ee8381c8b0d2814b5063777c764a114df295d6b06',
            'sig2' => '2e17af42b7c5df28d0e970689eb6b8e11d5c07c91d605a63e9b53cd92b9c9af6bc404626ded442062fea078d99d679f5d0bcb60d36825e51e9c45c7e52b90509',
        ),
        57 => 
        array (
            'nonce' => 'f19ca04cec554bb011229b8ee41e34cb',
            'timestamp' => '1788061793',
            'message' => '逐风工作室授权码管理平台|f19ca04cec554bb011229b8ee41e34cb|1788061793',
            'sig1' => 'ff34ac621f34585dc209d5879c4c1b0409075b0746843584ae211185ece88609adba5810930a6f9f7588de9d437a119e64e12600be650177f9c2b08bc36c8c0f',
            'sig2' => '72a42b1f3f6c4129d9552921ecad43e1689b6058460c3db3d91174cde9d6646505c7b5fe46194ddb58dbffb08cce771bff9dab821d45caf39220513622e78503',
        ),
        58 => 
        array (
            'nonce' => 'a3c3b1886c3aa240aa72ef4b6b1e6151',
            'timestamp' => '1788061794',
            'message' => '逐风工作室授权码管理平台|a3c3b1886c3aa240aa72ef4b6b1e6151|1788061794',
            'sig1' => '2b2f74dfef72b6a91b524965cfdc5155cbe54794ed6900b826136f3e0aa85d7db0103de8ce9cb66c8a1a4719147f4564fc8d149397ec17dd755b45d234bd5f01',
            'sig2' => '32656594383d2e1fcf05bf68da8e1f3fe0def93746f5e9f2bda40f4db1dff4d3a394bc7f10826c70776515d9548f65a0a9fa9765bb68fc7002ab07175df07b02',
        ),
        59 => 
        array (
            'nonce' => 'd7d41c12a18566d3b6548b66fc6982c2',
            'timestamp' => '1788061795',
            'message' => '逐风工作室授权码管理平台|d7d41c12a18566d3b6548b66fc6982c2|1788061795',
            'sig1' => 'd8676e9c14e2b063daef7c91ff15f4f19164d5279a30b085814c3600038864c86f70f2f64521eb9c2c339ff47d3343c762763c18eecb6e3020083e7f16fe470a',
            'sig2' => '22358d3edc9e592dc0abdd3f72fda48442577da27c09a29c36182ad18b6550b01b03f2d2057026bc56ba8700337c406005847f9916fa6d4e1c75a42558ed930f',
        ),
        60 => 
        array (
            'nonce' => 'd9bfb072bf48bec614b316a43898da49',
            'timestamp' => '1788061796',
            'message' => '逐风工作室授权码管理平台|d9bfb072bf48bec614b316a43898da49|1788061796',
            'sig1' => '3c331b922994524ced59e20dfbacfa5fd842dfb872c51b8ada9565469bc5832b5826b428d6c2c99666a995a736485a47b3cee0b26eb077b8c3f1c2d35d21120f',
            'sig2' => 'b5b6e5092380659260e1fd36a741f39083cb7c4c7cb689b9f65d3cc94210aff875f7d93764821bba853584e8b96f7433ea1c88e76c5bc23c454b7dadad81770b',
        ),
        61 => 
        array (
            'nonce' => '294575445bf155a1251c4c322690314c',
            'timestamp' => '1788061797',
            'message' => '逐风工作室授权码管理平台|294575445bf155a1251c4c322690314c|1788061797',
            'sig1' => '911a3b2534865b0aaab401bb93ede1a1cf268b811e6eb9a16cb62f58126f69c5a83d475d3699993f4b5d0053ed7b3855a58c549cfc37f27cb4f926851932580a',
            'sig2' => '90e1ebe765174915a8db3aa43f8a99420d36e6b900e6234f083ef49bf96dd13574d3b0c5ba43cd1eacc7ead2c937b72e5ab3066a77ebb33716f4f953e3e3e30a',
        ),
        62 => 
        array (
            'nonce' => 'b1c714bac18cb3705de7868a1686ee10',
            'timestamp' => '1788061798',
            'message' => '逐风工作室授权码管理平台|b1c714bac18cb3705de7868a1686ee10|1788061798',
            'sig1' => 'ab8eb8915ac3558a49c19bc41a874f0f021b2e5447d194883b3bba442c80c63fc3e3409a9b982491e088a4a3c7db88a74cd81483129f08da66e4711ac7a15201',
            'sig2' => '5dd512be06e5490c486706faee57095c1e9d1db644cb97b46bcb2fe576ca6f706fbf398aff3a9480ea17293351631d89d5b88d278fed3d1d1bef25ec57207b09',
        ),
        63 => 
        array (
            'nonce' => '5d1d123b00a621d26346585e995dc4fb',
            'timestamp' => '1788061799',
            'message' => '逐风工作室授权码管理平台|5d1d123b00a621d26346585e995dc4fb|1788061799',
            'sig1' => 'd731773ba337eccd780b685f56373303c9e363d3abac06a7b03136bd88d6a5c6aa83ba3383a54ea26033d7d18ae2c48c9dedeb73a3b4ab704c41c95353a6810b',
            'sig2' => '0df2e16df0e67695693bf4907c7b64d5e962e60e3afea82ebc58099e50b9e753a4452964e3198cb26763e0999304b7f63895ab31cf63be3e14590871e1880f0f',
        ),
        64 => 
        array (
            'nonce' => 'a8f02c0701a539fb742af2769813f9ec',
            'timestamp' => '1788061800',
            'message' => '逐风工作室授权码管理平台|a8f02c0701a539fb742af2769813f9ec|1788061800',
            'sig1' => 'bca7a12289add61898e711ef2268ba85b22eeada9eaa4a5d8b2cf437082634836dead9ffaf7ea8323ea73b71584af4d7bf5916f627c926fd0f3604912620e401',
            'sig2' => '07b4f7076ac0bf9c3f4e00d71c61668531d5f2ff621e3a90dcb441a3e4d8808bb574a55edc0c7d60ab08ce4f69279baafe10a262de1c7d2bfbe1462e0d702c0d',
        ),
        65 => 
        array (
            'nonce' => '4865be46308fd2e836f3a2404cc56e44',
            'timestamp' => '1788061801',
            'message' => '逐风工作室授权码管理平台|4865be46308fd2e836f3a2404cc56e44|1788061801',
            'sig1' => '3a4c260e69e8a7af20c373bfeeca6d917e25944d672d6263a515a90ab6b0a0fbfa32273b2a7a84110325c799984dfba7c669398ecdd0d9b62908ef4f90514304',
            'sig2' => '44ed770cef3c3af69a431d37f3e28c14c819f1f852cf9bfeb3f8dfd29b57c3f807158cbb199e1829abfd46a954f153f3ec815b01f61b8fb3fa3c9688a9bf360c',
        ),
        66 => 
        array (
            'nonce' => '41381ee2777f2089701a787af61afbf1',
            'timestamp' => '1788061802',
            'message' => '逐风工作室授权码管理平台|41381ee2777f2089701a787af61afbf1|1788061802',
            'sig1' => '5bdbbdfcc58bc37a59aff7163020a07e9208b7bab645b68dd3a5a65adce524da9bcfad149e9e5663f7fb73a738c7326aac447d6f0c129efd010140ff79870402',
            'sig2' => 'fe42e19eb11122a804075a789c15f1985580eaaabd60eb6d8dd300b2984bc52a10a4ae0d134337d3dd81ab15e10e3de2e70aa46e45cf63d477aa5208663ec601',
        ),
        67 => 
        array (
            'nonce' => 'f17173eb1e5a697f1819a53608695fae',
            'timestamp' => '1788061803',
            'message' => '逐风工作室授权码管理平台|f17173eb1e5a697f1819a53608695fae|1788061803',
            'sig1' => 'cec92ffa725a0073ebae8401d4794760b8540239d87bd771c39311b065c3a52039c6efd805270ec47dd914f7331996d3b217fbbcbeb9508860c5138b3426140f',
            'sig2' => 'f568e86790e19cc9f181ed2055a5cf4ff24a6c7a84ee65c93654dbf326f0df407efc943c52ba7dbc6b5f5bf4ae9a49143bcef692e100a7dec34e8230bf42cd0b',
        ),
        68 => 
        array (
            'nonce' => '10ba39e26e3cf16e5be3790ff9ab3bdc',
            'timestamp' => '1788061804',
            'message' => '逐风工作室授权码管理平台|10ba39e26e3cf16e5be3790ff9ab3bdc|1788061804',
            'sig1' => '8a5f225aff1eb9a2292102e7e0af59005de8b2de9fa75d196b746f45ac0fedb888fdec9e1f4b99bf632ac651e54ee73318282e74de84a816d2b5946c88dfd605',
            'sig2' => 'b0eaacd9d3737d8831815cf0fe28cfc471b2e33101e5633dd0142176d99c0c472d5bae54f8d60c7ca779b9f4da15fef6bd5e451cf6174e55783366df4e8b750b',
        ),
        69 => 
        array (
            'nonce' => '6fe8d285736df0cd6cd58435812e0a8b',
            'timestamp' => '1788061805',
            'message' => '逐风工作室授权码管理平台|6fe8d285736df0cd6cd58435812e0a8b|1788061805',
            'sig1' => 'dc12832ffdcd8a6aa1fd7f9573d530be1c36035c04b9d6d3513b62917602b4185ae7f1062d7dc80d24a5cf99d1bc9b66e080871fb91eaa3e6bf3ef7a2f21f604',
            'sig2' => 'bff5750b0f7985637d8b9c0de2c32f702b708d2dcb7fbc7863945747ea1c26fb8a051d0fa1d2e0c8a2204f670215de749a15241211ac398243a870d7459b0e06',
        ),
        70 => 
        array (
            'nonce' => '0ddf2c0b4da4c551c32c5ce136be9a88',
            'timestamp' => '1788061806',
            'message' => '逐风工作室授权码管理平台|0ddf2c0b4da4c551c32c5ce136be9a88|1788061806',
            'sig1' => '2b1db5919b03189717efd6bcb3c3a258d05158d0d1a22842aa8b5e9917714c2c64db64c2ae41530ffd7861445a86b1afcd79294598d3bb61728e026d48a1ef06',
            'sig2' => 'b61398c4e78b0b8cab248c024f28ef5b0f9837a7b2fcc21677c2aa8477db1bfe2681767176440221e07690e841e674d6b3e5c87879a6c0b8736e7957973c530d',
        ),
        71 => 
        array (
            'nonce' => 'e77bf8f31ec3ea02f37ae1582bc5ce4f',
            'timestamp' => '1788061807',
            'message' => '逐风工作室授权码管理平台|e77bf8f31ec3ea02f37ae1582bc5ce4f|1788061807',
            'sig1' => '0d2d7d8dc0b5c6b07162014d3f55caa7bc707d2520351f8e46221b6f2ce744963f39a2dc839a3b5eae15a12f311ddf86d605d4d0d3a403ba33804f64db4bf008',
            'sig2' => '9a8b96a01eb5f8ada51bc4a517a8d584d8bb153e27f9684d0ac4bec4008927052c123c16336ee9aa1bc77468c7be813f46d6f70ed284df8c265d35c32f557f08',
        ),
        72 => 
        array (
            'nonce' => 'cbb1047d2877609ef46f095268386052',
            'timestamp' => '1788061808',
            'message' => '逐风工作室授权码管理平台|cbb1047d2877609ef46f095268386052|1788061808',
            'sig1' => 'c7cd105897fdb65718794c92d90396a2d6d2882285a4ec398aff14f017c18be9f083455126d5a81fca60458af82391e92e9a5f68d9942ec6e34e307222d9b20f',
            'sig2' => '7ca3ce8fc440bb0c6718826b32c360704f28210c65352989753cfb47ea8b79d773c5e08b878952c68c6b4ce6368d6162864ba5bbb6d996b95a763cde8c37a903',
        ),
        73 => 
        array (
            'nonce' => '0c7134c6c390e69d05558c73fafd2318',
            'timestamp' => '1788061809',
            'message' => '逐风工作室授权码管理平台|0c7134c6c390e69d05558c73fafd2318|1788061809',
            'sig1' => 'dfb09321fa9290fc3ba973a19edbc9608c72d4fdf5d767e96ad877bb0089170d66ec1d54bce3d1567df32b9080a4fa4c6e55e484cc4118b917e79271af164c0d',
            'sig2' => '9018c904f57efb5f7cd00a80fa6d45500550b86b57d931fe9eeafe6d237023a80e86f09d03417e622a20dc73c740c959fb585bb7ccf3171bf88f820596178607',
        ),
        74 => 
        array (
            'nonce' => '3bee8fa89d24920e2f78e3808f56c9df',
            'timestamp' => '1788061810',
            'message' => '逐风工作室授权码管理平台|3bee8fa89d24920e2f78e3808f56c9df|1788061810',
            'sig1' => 'dcef8494d4ba83183d15280eab25f7b29368ecfdfeec55c3230188d11151c2f76b6ec3007156abfb98b4ee5a758e099c276764ff00cef343934fb30210c83601',
            'sig2' => '9acce97489a160f53a5ec0d16c93ef58ec3c3a2a8d5791866a8d82824be1f5ff80d60b993dc65ac081f80f0b99cdc9318b1c9c96b09fef732058044e0e9f770b',
        ),
        75 => 
        array (
            'nonce' => '555f5b9a34e12c3c29b0550e765b2f46',
            'timestamp' => '1788061811',
            'message' => '逐风工作室授权码管理平台|555f5b9a34e12c3c29b0550e765b2f46|1788061811',
            'sig1' => 'a66e76cca5e1644e6fb2eef39f03b1b1add22097c3c9c5204ed9a003d8a0dd8782f73cf750105c42bb76d9e450cfc7dfece220854a3d8d1a639c810ed9fa4700',
            'sig2' => 'e1f9a609fd8819a6fca05fa1a565e18b3a079ceca9a0d845b64192d1b2c09d27e9bb0e4188df0a2d182044b9af47f9f719a3ddef472ae99cebc01bf2cbf8610c',
        ),
        76 => 
        array (
            'nonce' => '4636b761128987152b8580715911feb8',
            'timestamp' => '1788061812',
            'message' => '逐风工作室授权码管理平台|4636b761128987152b8580715911feb8|1788061812',
            'sig1' => 'efc9ce59bf857feea9bbcd47fb707e35916c760c55dead73ff617cb3d8b1f2cf7e15fb462231a650d3a4ec4e7f9924c910b9ba964626474630bb7ade68429009',
            'sig2' => 'ae687ed9771134fbffd04cf6464bed90afa4afe39c9eeecde0098b28ac58cc12b13b6c9a72310dc96f8bc4248bf3a64b32e6dce193d834d298b7e10ad1d1770f',
        ),
        77 => 
        array (
            'nonce' => '5fc6e749df34eb3b721ec364afe2ccb9',
            'timestamp' => '1788061813',
            'message' => '逐风工作室授权码管理平台|5fc6e749df34eb3b721ec364afe2ccb9|1788061813',
            'sig1' => 'e229901d5f8f8b20454d3eb1af20f108496aff61a3e3bb67ec43ee50e3f58fc34b74c4fe055bedd17c82d02d14e5070c72be2e95595d1bf39338c68bcde26308',
            'sig2' => '98c60ce272fbd8e894aaa5b74ecdf597b7b5a8b6707278fd46f0ff8c20384943f3be33a9b1fddafc3fd00c9cf2e905d812cc421ba7c95197e46127974e47a100',
        ),
        78 => 
        array (
            'nonce' => '8a40be0bf0c99f56a4fe343d69a32609',
            'timestamp' => '1788061814',
            'message' => '逐风工作室授权码管理平台|8a40be0bf0c99f56a4fe343d69a32609|1788061814',
            'sig1' => '80f5eb718c6becf5e282dade20d00c5de377506909e9c3ed1f87bafab1907dee85445a1813e979e0b14462b856bde5bb0ca5544daa292a6a690e22bbaa48cd0c',
            'sig2' => '89882512221a531e502d8c46709455364d06fbbdf214a2072ea372d29ad99dad139a38b571a25564640c30696b5fce0d42dc941a1bf0f41da124f9599982170d',
        ),
        79 => 
        array (
            'nonce' => '2b06d5913db621c41201c134c7fd9973',
            'timestamp' => '1788061815',
            'message' => '逐风工作室授权码管理平台|2b06d5913db621c41201c134c7fd9973|1788061815',
            'sig1' => '7d58dbc330ef98fa13d25e3e1908b55de545dc7418580513e07f0dfbba7223329d8531ec71adca4833a52786c4870ca93cd60def964e01507801662694592701',
            'sig2' => 'dd44ea781e5ea794bd029896cd666b80201ea507808e0c5aff6186f21270054c194a7474e496b770b9f32d5f5b5de0398a0829ffed75281503dc9c1a9b45de06',
        ),
        80 => 
        array (
            'nonce' => 'a9bdd55bea1772cc9ca77954fbb6c807',
            'timestamp' => '1788061816',
            'message' => '逐风工作室授权码管理平台|a9bdd55bea1772cc9ca77954fbb6c807|1788061816',
            'sig1' => '6d2664522a0502ce06c046eb17eae81bef6f84f18d61045fe9d7c3f0889238b555d19c31252d464d9f38a94ddcbd9d3ffe9cabdf3f470af43913fcd0ad2a1803',
            'sig2' => '9a11bcfa91756a52af071bf99b135ddb99d978c8a6b0846d989c4f145c91d17674bbcd93004c17f12ba4208e95e7791fb3054c60056d5045a5ff49d5a9e8ae0c',
        ),
        81 => 
        array (
            'nonce' => '581d3e477758a32c0f4ff279dbf30157',
            'timestamp' => '1788061817',
            'message' => '逐风工作室授权码管理平台|581d3e477758a32c0f4ff279dbf30157|1788061817',
            'sig1' => 'c8ce6a83d77e1c5775e073435c5187d99b6ae2935148fe01995dad539ccfe4655b207d78db09cea96b50b20f874d16cf87c577627aba11bf9765fc47bc634707',
            'sig2' => 'c78d455b71aed747617590c37fa4a00b5c288c140d3b7aef5da651721dfa759e74d58687d2641b775b4ca84e78e752a31f536eb25dc62afa66201c5a0c71180f',
        ),
        82 => 
        array (
            'nonce' => '29d989cc358ef6cf26fc1151c6bf2acd',
            'timestamp' => '1788061818',
            'message' => '逐风工作室授权码管理平台|29d989cc358ef6cf26fc1151c6bf2acd|1788061818',
            'sig1' => 'da127d4669a9d9a561ec7a583f781c7ee07fb4ea9464587f2edbf30e39b717febbcbdab01e9cc337d8d5872d1289877f0cc7c2a9900bf3fce94e0b295a0bca0e',
            'sig2' => 'b8346c3f9c3905cf119d19133118aba3666c6eb2f45849ceeaaaa1e8ff430a3b8753cd20852c16ce077eaab4097e1876a56e0e5034030fa5010d4c4e7a48a109',
        ),
        83 => 
        array (
            'nonce' => 'd1b7c28487531d9fc24e67b7e0de1c3d',
            'timestamp' => '1788061819',
            'message' => '逐风工作室授权码管理平台|d1b7c28487531d9fc24e67b7e0de1c3d|1788061819',
            'sig1' => '51cbf435c3236f084b690b2d778a612995810aeca0f173a78b1de0fc9720bc422c46d6c4f5754adf32e185794c40088233d689ca92b0464e80917c260ec63105',
            'sig2' => '0532438e0394adc779ff8400f60f7c85b192bed98e1a30e667e0d38939166761844bf548cc666641de1887fe0916d17e1a00ad49d59ae50ae868d8143c32270e',
        ),
        84 => 
        array (
            'nonce' => '8b8ec3ecdd5ee3de4d9e6f7de47ab85d',
            'timestamp' => '1788061820',
            'message' => '逐风工作室授权码管理平台|8b8ec3ecdd5ee3de4d9e6f7de47ab85d|1788061820',
            'sig1' => '111f7e8a40941b42dfd7f976723ffd872997e8d0bf5eeee6084dee49ba9bf0081e183de2d467d08fb08ef6be806b693b0d51fdfba3c13078903ff53e7b51ac03',
            'sig2' => '23f32e0b44b6c8aecf3c23ca7554fd704c5c6e9ec47067cea9e7c616fdbf0471d66515d062d3b1b2bf0e7f7bebc75543b14e78815da40f6915fcd41458c0cc03',
        ),
        85 => 
        array (
            'nonce' => 'f747c54486ffd1d33bd176410d0b4399',
            'timestamp' => '1788061821',
            'message' => '逐风工作室授权码管理平台|f747c54486ffd1d33bd176410d0b4399|1788061821',
            'sig1' => 'ee04066aa631b3372de098b23acfd0b11762cd8f8ecafb7b97136a5102a4b1f31f259129642e33dc59283a6228d70f7424275bd86aeb2ba18b5b09e1d6a26e09',
            'sig2' => '1bad7d3f1662a743503f6f8801b861d063dde5867a7100af18301ca94a39dd44a2689343a9c7d36a266c5aa2e3df19617b0d1fd256413aba8f4f6971e06e5c03',
        ),
        86 => 
        array (
            'nonce' => 'f7fb34af8c47adda71f16cc678a13a22',
            'timestamp' => '1788061822',
            'message' => '逐风工作室授权码管理平台|f7fb34af8c47adda71f16cc678a13a22|1788061822',
            'sig1' => 'df82926edc17aa1fb9cf0e3b62f759d0431cd8965ca56c3e005db32f4eabe55fb52bde48df4693c6b23fc7d037c9f6d904df058eb64d98010a8b70a42317f807',
            'sig2' => '337705669b91abd1241b6aeeaab656f14a60c1c303adb1fedd1b25d35638863cd715b37b1e71843527879148ec5d3372f410975114faaabcf0bb60cb83789c0d',
        ),
        87 => 
        array (
            'nonce' => 'be640f9eb9be7f06a23c70e32f588204',
            'timestamp' => '1788061823',
            'message' => '逐风工作室授权码管理平台|be640f9eb9be7f06a23c70e32f588204|1788061823',
            'sig1' => '72538dc7fedd4fba5c9411c6a3f40e67dc0192bd3e7a8aca7b0556eba97cb38a9ec197a82f50ac3a49e87715863bd21f7519c5eae8a0167505ee63313faa7b03',
            'sig2' => '73c9c66628e64d08fdde11b10abe862ff164cbebe7989e1070fddc8e586430b638166963899f5b793ceea907960d345c460aa2c7a1bd2689858a9c780b2de703',
        ),
        88 => 
        array (
            'nonce' => 'c5ca1ed1de880ba667fe9a815a8eedf3',
            'timestamp' => '1788061824',
            'message' => '逐风工作室授权码管理平台|c5ca1ed1de880ba667fe9a815a8eedf3|1788061824',
            'sig1' => 'c7a632493bbcbd506b2455ee8b312b45e55c14c394294e7a67726300e44f09554f1a2629acdc71baab2284b05789b18b8997e17b8a692124c153ee152f62df0c',
            'sig2' => 'b79c772eba812f85d74534c9e022570250f06ecadd324c33313e1fdfa50231798fdceb819b712af7a19e0b0eb8e57b931b719121e7b96a762513a841eab90400',
        ),
        89 => 
        array (
            'nonce' => '5282d69e2a5327e6d97f0b22cc995625',
            'timestamp' => '1788061825',
            'message' => '逐风工作室授权码管理平台|5282d69e2a5327e6d97f0b22cc995625|1788061825',
            'sig1' => 'e525745f0ffaa967339692d895f0c63f9394c0ebe56e23d871bdab5cd545be661848c15d058616acd83fb228f9a6a1427b8652372b4aa87e8b84f6781db3af0d',
            'sig2' => '561e341f8e21a1df93512a83dc29242d2aaf6d7f9672059a383f4006c51537492bbe5229c2ba28f1e23d3deefa59434142777062562bb3bfc385be037562fc0b',
        ),
        90 => 
        array (
            'nonce' => '4f3055fcf5b669e4eb679611563bc74c',
            'timestamp' => '1788061826',
            'message' => '逐风工作室授权码管理平台|4f3055fcf5b669e4eb679611563bc74c|1788061826',
            'sig1' => '4a73d237d5421ffcc50c7b7c921e97b27e7ea0ade2cd26ae7843c741a1d7c501187649619305988af007819c27af29a0ccdcc481f8feb8193541ca332402540e',
            'sig2' => '9c7a0df04e0dbb618d2f31414d7fec0d11ebe013284ac7c1e4c27c55a8017f62d9d134fafe3f98643327c22c931b51641b54fe4350acece24ca8febca36c0b03',
        ),
        91 => 
        array (
            'nonce' => '96b6d3526f637887dfad814cb6716915',
            'timestamp' => '1788061827',
            'message' => '逐风工作室授权码管理平台|96b6d3526f637887dfad814cb6716915|1788061827',
            'sig1' => '01f7b265db5d5a3f2e24a40d1eb9045f0851aeed1b7b177f26bd0421e778855009447df36e4c3b0d0bc5cff47f7261fee1169879d8e9a91207b0c5a6047da607',
            'sig2' => 'bc2a7eaa61a1b2347fc2b108f45cd1877426413c5a7dda43a7a253c48d227d626b66dd4e83b4f2131b9fa8adfd5661367276258617912b35ecf97a878b2f9500',
        ),
        92 => 
        array (
            'nonce' => '091284f5108c2218fc90dff68e67dc17',
            'timestamp' => '1788061828',
            'message' => '逐风工作室授权码管理平台|091284f5108c2218fc90dff68e67dc17|1788061828',
            'sig1' => 'bafa1ade62531d14a78b6f690ca79893055c587579ab7700fa25b00fc79c478f1e68249148ffa059fe2b7a74638137f6eea69ee298072a40f1cd45d628e5f30c',
            'sig2' => '8fa770ddc1ead898d48b3f4a1aeef9490608e48540cc16079eaa746216b64d987bced28b32f9fd6c9bade608cd287d8da1c20dc00f9e632be5617c6cae73360f',
        ),
        93 => 
        array (
            'nonce' => '27e71387da8f9b88e9dbe4c3ba9e9c08',
            'timestamp' => '1788061829',
            'message' => '逐风工作室授权码管理平台|27e71387da8f9b88e9dbe4c3ba9e9c08|1788061829',
            'sig1' => 'ef8546efa7213741483068276952cc30cfedccc8c42320ca8c219c3537af94e8ceae731eb0b9f1f5a5cbb955d2bcc203f0a250aea48dfef033987ef8b5f8670a',
            'sig2' => 'a8fc1ccc554fc216d461025e88b46d18224cd233522b5aae9e920fd7acae9c81af2d7dfbbe033b0845a39e3d8daeda6c5019b37aac404fa067deea07cfbdc002',
        ),
        94 => 
        array (
            'nonce' => '03fd51849eab52e847482e8ed15e7667',
            'timestamp' => '1788061830',
            'message' => '逐风工作室授权码管理平台|03fd51849eab52e847482e8ed15e7667|1788061830',
            'sig1' => '707e52cff85eb28fe2395a5c45253dd8ccb9068f56946aa52ede3229df2e3e131f6bf9be60481e3f92746d959f481e139fcddee282df35cefdb36526c5b8730b',
            'sig2' => 'ede1359e2db857dc53b1b264597c775b50a2340ff1ab72c6ceef588299346fa486c37fd8c5b367b4acac9c06422ac3f4546747589a60c2a751646b21a1a25405',
        ),
        95 => 
        array (
            'nonce' => '5d036215b3943f4bf84603da2672565f',
            'timestamp' => '1788061831',
            'message' => '逐风工作室授权码管理平台|5d036215b3943f4bf84603da2672565f|1788061831',
            'sig1' => 'f792a504ba66edae0862e18eff505cb8f68298cef97d6b5900d8ac2f59127eecf8c259d3f0869f211cab48a518a14a6c608e1cedb2f4aa9ad3b2157a172f2b05',
            'sig2' => '051cd041077f55d0851b304eec84778152a61ed49858eee392b181d032ff456ede4f38d0dd216a66963924bbea42527a16954bb0fd5651a147621146ed221c04',
        ),
    ),
    'total' => 96,
    'owner1' => 'd78eda122c0b36f25a278b08b5e4dea2fb7961fe0842de115d8abc49099ae86b',
    'owner2' => '6637bebc7b0d9c0a508a513c36e06082dd216016cce9b6d2c0c2d390e4468bcf',
);
