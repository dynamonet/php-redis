#!lua name=mylib

local function my_hset(keys, args)
    local hash = keys[1]
    local time = redis.call('TIME')[1]
    return redis.call('HSET', hash, '_last_modified_', time, unpack(args))
  end
  
  local function my_hgetall(keys, args)
    redis.setresp(3)
    local hash = keys[1]
    local res = redis.call('HGETALL', hash)
    res['map']['_last_modified_'] = nil
    return res
  end
  
  local function my_hlastmodified(keys, args)
    local hash = keys[1]
    return redis.call('HGET', keys[1], '_last_modified_')
  end
  
  redis.register_function('my_hset', my_hset)
  redis.register_function('my_hgetall', my_hgetall)
  redis.register_function('my_hlastmodified', my_hlastmodified)