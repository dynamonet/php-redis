#!lua name=tslib

local function tbadd(keys, args)
    local keyprefix = keys[1]
    local incr = tonumber(keys[2]) or 1
    local expire = (tonumber(args[1]) or 60) + 2
    local rcall = redis.call
    local result = 0
    
    local time = rcall("TIME")
    local seconds = time[1]
    
    local key = keyprefix .. ":" .. seconds
    result = rcall("INCRBY", key, incr)
    rcall("EXPIRE", key, expire)
    
    return { key, result }
end

-- Calculates the AVERAGE in the last N samples
local function tbavg(keys, args)
    local keyprefix = keys[1]
    local samples = tonumber(keys[2]) or 60
    local rcall = redis.call
    local result = 0
    
    local time = rcall("TIME")
    local seconds = time[1]

    local samplekeys = {}

    for i = 1, samples do
        -- the current bucket is not taken into account because is considered to be incomplete
        samplekeys[i] = keyprefix .. ":" .. (seconds - samples - 1 + i)
    end

    local samplevalues = rcall("MGET", unpack(samplekeys))
    
    local totalsum = 0
    
    for _, val in ipairs(samplevalues) do
        totalsum = totalsum + (val or 0)
    end

    local avg = tostring(totalsum / #samplevalues)
    
    return avg 
end

redis.register_function('tbadd', tbadd)
redis.register_function('tbavg', tbavg)
